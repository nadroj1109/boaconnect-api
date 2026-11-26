<?php

namespace Tests\Unit;

use App\Models\BlockchainAnchor;
use App\Models\DocumentFile;
use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use Web3p\EthereumUtil\Util;

class MainnetAnchorSubmissionTest extends TestCase
{
    private const CONTRACT =
        '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD';

    // Public Hardhat test account. Never use outside automated tests.
    private const TEST_SIGNER =
        '0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266';

    private const TEST_PRIVATE_KEY =
        'ac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'blockchain.enabled' => true,
            'blockchain.network' => 'mainnet',
            'blockchain.mainnet.chain_id' => 1,
            'blockchain.mainnet.rpc_url' =>
                'https://mainnet-test.example',
            'blockchain.mainnet.contract_address' => self::CONTRACT,
            'blockchain.mainnet.signer_address' => self::TEST_SIGNER,
            'blockchain.mainnet.private_key' => self::TEST_PRIVATE_KEY,
            'blockchain.mainnet.auto_submit' => true,
            'blockchain.mainnet.max_priority_fee_gwei' => 3,
            'blockchain.mainnet.max_base_fee_gwei' => 100,
            'blockchain.mainnet.max_total_fee_gwei' => 200,
            'cache.default' => 'array',
        ]);

        Schema::create('blockchain_anchors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('document_file_id')->nullable();
            $table->unsignedBigInteger('document_payment_id')->nullable();
            $table->string('proof_type');
            $table->unsignedInteger('proof_version')->default(1);
            $table->string('proof_hash', 64);
            $table->string('network', 32);
            $table->unsignedBigInteger('chain_id');
            $table->string('contract_address', 42)->nullable();
            $table->string('transaction_hash', 66)->nullable();
            $table->uuid('submission_claim_token')->nullable();
            $table->timestamp('submission_claimed_at')->nullable();
            $table->unsignedBigInteger('block_number')->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['document_file_id', 'proof_version', 'network'],
                'test_document_version_network_unique'
            );

            $table->unique(
                ['document_payment_id', 'proof_version', 'network'],
                'test_payment_version_network_unique'
            );
        });

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function makeAnchor(array $overrides = []): BlockchainAnchor
    {
        return BlockchainAnchor::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'document_file_id' => 42,
            'document_payment_id' => null,
            'proof_type' => BlockchainAnchor::TYPE_DOCUMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('a', 64),
            'network' => 'mainnet',
            'chain_id' => 1,
            'contract_address' => self::CONTRACT,
            'transaction_hash' => null,
            'status' => BlockchainAnchor::STATUS_PENDING,
            'attempts' => 0,
        ], $overrides));
    }

    private function makeService(BlockchainAnchor $anchor): BlockchainProofService
    {
        $service = Mockery::mock(BlockchainProofService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        // Validation and transaction preparation each check readiness.
        $service->shouldReceive('assertReady')
            ->twice()
            ->with('mainnet');

        $service->shouldReceive('isWriter')
            ->once()
            ->with(self::TEST_SIGNER, 'mainnet')
            ->andReturn(true);

        $file = new DocumentFile();
        $file->id = (int) $anchor->document_file_id;

        $service->shouldReceive('findDocumentFile')
            ->once()
            ->with((int) $anchor->document_file_id)
            ->andReturn($file);

        $service->shouldReceive('prepareDocumentProof')
            ->once()
            ->with($file)
            ->andReturn([
                'proof_type' => 'document',
                'proof_version' => 1,
                'document_file_id' => (int) $anchor->document_file_id,
                'document_uuid' => 'test-document-uuid',
                'proof_hash' => (string) $anchor->proof_hash,
            ]);

        return $service;
    }

    /**
     * Fake the Mainnet JSON-RPC methods used by the real submission flow.
     *
     * The broadcast handler calculates the expected transaction hash from
     * the actual signed raw transaction. It also checks whether the hash
     * was persisted before eth_sendRawTransaction was called.
     *
     * $broadcastMode: success | timeout | mismatch
     */
    private function fakeMainnetRpc(
        BlockchainAnchor $anchor,
        string $broadcastMode = 'success',
        ?array &$observations = null
    ): void {
        $observations = [
            'broadcast_count' => 0,
            'hash_persisted_before_broadcast' => false,
            'local_hash' => null,
        ];

        Http::fake(function (Request $request) use (
            $anchor,
            $broadcastMode,
            &$observations
        ) {
            $payload = json_decode($request->body(), true);
            $method = $payload['method'] ?? '';

            $result = match ($method) {
                'eth_chainId' => '0x1',
                'eth_getCode' => '0x6000',
                // ABI response: five zeroed 32-byte words.
                'eth_call' => '0x' . str_repeat('0', 320),
                'eth_getTransactionCount' => '0x0',
                'eth_estimateGas' => '0x5208',
                'eth_maxPriorityFeePerGas' =>
                    '0x' . dechex(2_000_000_000),
                'eth_feeHistory' => [
                    'baseFeePerGas' => [
                        '0x' . dechex(80_000_000_000),
                        '0x' . dechex(80_000_000_000),
                    ],
                ],
                default => null,
            };

            if ($method === 'eth_sendRawTransaction') {
                $observations['broadcast_count']++;

                $rawTransaction = $payload['params'][0] ?? '';

                if (
                    ! is_string($rawTransaction)
                    || ! preg_match(
                        '/^0x02(?:[a-fA-F0-9]{2})+$/',
                        $rawTransaction
                    )
                ) {
                    return Http::response([
                        'jsonrpc' => '2.0',
                        'id' => $payload['id'] ?? 1,
                        'error' => [
                            'code' => -32602,
                            'message' => 'Invalid raw transaction.',
                        ],
                    ], 200);
                }

                $rawBytes = hex2bin(substr($rawTransaction, 2));

                if ($rawBytes === false) {
                    throw new RuntimeException(
                        'Unable to decode the signed test transaction.'
                    );
                }

                $localHash = '0x' . strtolower(
                    (new Util())->sha3($rawBytes)
                );

                $observations['local_hash'] = $localHash;

                $savedAnchor = BlockchainAnchor::query()->findOrFail(
                    $anchor->id
                );

                $observations['hash_persisted_before_broadcast'] =
                    hash_equals(
                        strtolower((string) $savedAnchor->transaction_hash),
                        strtolower($localHash)
                    );

                if ($broadcastMode === 'timeout') {
                    return Http::response([
                        'error' => 'Simulated RPC timeout',
                    ], 500);
                }

                if ($broadcastMode === 'mismatch') {
                    return Http::response([
                        'jsonrpc' => '2.0',
                        'id' => $payload['id'] ?? 1,
                        'result' => '0x' . str_repeat('f', 64),
                    ], 200);
                }

                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => $payload['id'] ?? 1,
                    'result' => $localHash,
                ], 200);
            }

            if ($result === null) {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => $payload['id'] ?? 1,
                    'error' => [
                        'code' => -32601,
                        'message' => 'Method not mocked: ' . $method,
                    ],
                ], 200);
            }

            return Http::response([
                'jsonrpc' => '2.0',
                'id' => $payload['id'] ?? 1,
                'result' => $result,
            ], 200);
        });
    }

    public function test_submits_a_signed_mainnet_transaction_and_persists_hash_before_broadcast(): void
    {
        $anchor = $this->makeAnchor();
        $service = $this->makeService($anchor);

        $observations = [];
        $this->fakeMainnetRpc($anchor, 'success', $observations);

        $result = $service->submitAnchorOnMainnetAutomatically($anchor);

        $saved = $anchor->fresh();

        $this->assertSame('submitted', $result['state']);
        $this->assertSame(
            $observations['local_hash'],
            $result['transaction_hash']
        );

        $this->assertSame(
            $observations['local_hash'],
            $saved->transaction_hash
        );

        $this->assertSame(
            BlockchainAnchor::STATUS_SUBMITTED,
            $saved->status
        );

        $this->assertSame(1, (int) $saved->attempts);
        $this->assertNotNull($saved->submitted_at);

        $this->assertSame(1, $observations['broadcast_count']);
        $this->assertTrue(
            $observations['hash_persisted_before_broadcast']
        );

        $this->assertNull($saved->submission_claim_token);
        $this->assertNull($saved->submission_claimed_at);
        $this->assertNull($saved->last_error);
    }

    public function test_retains_transaction_hash_when_broadcast_result_is_uncertain(): void
    {
        $anchor = $this->makeAnchor();
        $service = $this->makeService($anchor);

        $observations = [];
        $this->fakeMainnetRpc($anchor, 'timeout', $observations);

        $result = $service->submitAnchorOnMainnetAutomatically($anchor);

        $saved = $anchor->fresh();

        $this->assertSame(
            'reconciliation_required',
            $result['state']
        );

        $this->assertSame(
            $observations['local_hash'],
            $saved->transaction_hash
        );

        $this->assertSame(
            $observations['local_hash'],
            $result['transaction_hash']
        );

        $this->assertTrue(
            $observations['hash_persisted_before_broadcast']
        );

        $this->assertSame(1, $observations['broadcast_count']);
        $this->assertNotNull($saved->last_error);
        $this->assertNull($saved->submission_claim_token);
        $this->assertNull($saved->submission_claimed_at);
    }

    public function test_retains_local_hash_when_rpc_returns_a_different_hash(): void
    {
        $anchor = $this->makeAnchor();
        $service = $this->makeService($anchor);

        $observations = [];
        $this->fakeMainnetRpc($anchor, 'mismatch', $observations);

        $result = $service->submitAnchorOnMainnetAutomatically($anchor);

        $saved = $anchor->fresh();

        $this->assertSame(
            'reconciliation_required',
            $result['state']
        );

        $this->assertSame(
            $observations['local_hash'],
            $saved->transaction_hash
        );

        $this->assertNotSame(
            '0x' . str_repeat('f', 64),
            $saved->transaction_hash
        );

        $this->assertTrue(
            $observations['hash_persisted_before_broadcast']
        );

        $this->assertSame(1, $observations['broadcast_count']);
        $this->assertNotNull($saved->last_error);
        $this->assertNull($saved->submission_claim_token);
    }

    public function test_does_not_broadcast_again_when_transaction_hash_already_exists(): void
    {
        $existingHash = '0x' . str_repeat('b', 64);

        $anchor = $this->makeAnchor([
            'transaction_hash' => $existingHash,
        ]);

        $service = app(BlockchainProofService::class);

        Http::fake();

        $result = $service->submitAnchorOnMainnetAutomatically($anchor);

        $this->assertSame(
            'reconciliation_required',
            $result['state']
        );

        $this->assertSame(
            $existingHash,
            $result['transaction_hash']
        );

        Http::assertNothingSent();

        $this->assertSame(
            $existingHash,
            $anchor->fresh()->transaction_hash
        );
    }
}
