<?php

namespace Tests\Unit;

use App\Models\BlockchainAnchor;
use App\Models\DocumentFile;
use App\Models\DocumentPayment;
use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SepoliaAnchorCreationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'blockchain.enabled' => true,
            'blockchain.network' => 'sepolia',
            'blockchain.sepolia.chain_id' => 11155111,
            'blockchain.sepolia.contract_address' =>
                '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD',
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
    }

    public function test_creates_pending_sepolia_document_anchor(): void
    {
        $file = new DocumentFile();
        $file->id = 42;

        $service = Mockery::mock(BlockchainProofService::class)
            ->makePartial();

        $service->shouldReceive('prepareDocumentProof')
            ->once()
            ->with($file)
            ->andReturn([
                'proof_type' => 'document',
                'proof_version' => 1,
                'document_file_id' => 42,
                'document_uuid' => 'test-document-uuid',
                'proof_hash' => str_repeat('a', 64),
            ]);

        $anchor = $service->createSepoliaDocumentAnchor($file);

        $this->assertInstanceOf(BlockchainAnchor::class, $anchor);
        $this->assertNotEmpty($anchor->uuid);
        $this->assertSame(42, $anchor->document_file_id);
        $this->assertNull($anchor->document_payment_id);
        $this->assertSame(BlockchainAnchor::TYPE_DOCUMENT, $anchor->proof_type);
        $this->assertSame(str_repeat('a', 64), $anchor->proof_hash);
        $this->assertSame('sepolia', $anchor->network);
        $this->assertSame(11155111, $anchor->chain_id);
        $this->assertSame(
            '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD',
            $anchor->contract_address
        );
        $this->assertSame(BlockchainAnchor::STATUS_PENDING, $anchor->status);
        $this->assertSame(0, $anchor->attempts);
        $this->assertDatabaseCount('blockchain_anchors', 1);
    }

    public function test_creates_pending_sepolia_payment_anchor(): void
    {
        $payment = new DocumentPayment();
        $payment->id = 84;

        $service = Mockery::mock(BlockchainProofService::class)
            ->makePartial();

        $service->shouldReceive('preparePaymentProof')
            ->once()
            ->with($payment)
            ->andReturn([
                'proof_type' => 'payment',
                'proof_version' => 1,
                'payment_id' => 84,
                'proof_hash' => str_repeat('b', 64),
            ]);

        $anchor = $service->createSepoliaPaymentAnchor($payment);

        $this->assertInstanceOf(BlockchainAnchor::class, $anchor);
        $this->assertNotEmpty($anchor->uuid);
        $this->assertNull($anchor->document_file_id);
        $this->assertSame(84, $anchor->document_payment_id);
        $this->assertSame(BlockchainAnchor::TYPE_PAYMENT, $anchor->proof_type);
        $this->assertSame(str_repeat('b', 64), $anchor->proof_hash);
        $this->assertSame('sepolia', $anchor->network);
        $this->assertSame(11155111, $anchor->chain_id);
        $this->assertSame(
            '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD',
            $anchor->contract_address
        );
        $this->assertSame(BlockchainAnchor::STATUS_PENDING, $anchor->status);
        $this->assertSame(0, $anchor->attempts);
        $this->assertDatabaseCount('blockchain_anchors', 1);
    }

    public function test_reuses_existing_matching_sepolia_payment_anchor(): void
    {
        $payment = new DocumentPayment();
        $payment->id = 84;

        $proofHash = str_repeat('b', 64);

        $existing = BlockchainAnchor::query()->create([
            'document_file_id' => null,
            'document_payment_id' => 84,
            'proof_type' => BlockchainAnchor::TYPE_PAYMENT,
            'proof_version' => 1,
            'proof_hash' => $proofHash,
            'network' => 'sepolia',
            'chain_id' => 11155111,
            'contract_address' =>
                '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD',
            'status' => BlockchainAnchor::STATUS_PENDING,
            'attempts' => 0,
        ]);

        $service = Mockery::mock(BlockchainProofService::class)
            ->makePartial();

        $service->shouldReceive('preparePaymentProof')
            ->once()
            ->with($payment)
            ->andReturn([
                'proof_type' => 'payment',
                'proof_version' => 1,
                'payment_id' => 84,
                'proof_hash' => $proofHash,
            ]);

        $anchor = $service->createSepoliaPaymentAnchor($payment);

        $this->assertSame($existing->id, $anchor->id);
        $this->assertSame(1, BlockchainAnchor::query()->count());
    }

    public function test_rejects_existing_sepolia_payment_anchor_with_different_hash(): void
    {
        $payment = new DocumentPayment();
        $payment->id = 84;

        BlockchainAnchor::query()->create([
            'document_file_id' => null,
            'document_payment_id' => 84,
            'proof_type' => BlockchainAnchor::TYPE_PAYMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('c', 64),
            'network' => 'sepolia',
            'chain_id' => 11155111,
            'contract_address' =>
                '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD',
            'status' => BlockchainAnchor::STATUS_PENDING,
            'attempts' => 0,
        ]);

        $service = Mockery::mock(BlockchainProofService::class)
            ->makePartial();

        $service->shouldReceive('preparePaymentProof')
            ->once()
            ->with($payment)
            ->andReturn([
                'proof_type' => 'payment',
                'proof_version' => 1,
                'payment_id' => 84,
                'proof_hash' => str_repeat('b', 64),
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'An existing Sepolia payment anchor has a different proof hash.'
        );

        $service->createSepoliaPaymentAnchor($payment);
    }

    public function test_rejects_anchor_with_wrong_sepolia_chain_id(): void
    {
        $anchor = BlockchainAnchor::query()->create([
            'document_file_id' => null,
            'document_payment_id' => 84,
            'proof_type' => BlockchainAnchor::TYPE_PAYMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('b', 64),
            'network' => 'sepolia',
            'chain_id' => 31337,
            'contract_address' =>
                '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD',
            'status' => BlockchainAnchor::STATUS_PENDING,
            'attempts' => 0,
        ]);

        $service = Mockery::mock(BlockchainProofService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $service->shouldReceive('assertReady')
            ->once()
            ->with('sepolia');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'The anchor network, chain ID, or contract does not match Sepolia configuration.'
        );

        $service->validateSepoliaAnchorForSubmission($anchor);
    }

    public function test_marks_sepolia_document_anchor_mismatch_when_source_hash_changes(): void
    {
        $file = new DocumentFile();
        $file->id = 42;

        $anchor = BlockchainAnchor::query()->create([
            'document_file_id' => 42,
            'document_payment_id' => null,
            'proof_type' => BlockchainAnchor::TYPE_DOCUMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('a', 64),
            'network' => 'sepolia',
            'chain_id' => 11155111,
            'contract_address' =>
                '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD',
            'status' => BlockchainAnchor::STATUS_PENDING,
            'attempts' => 0,
        ]);

        $service = Mockery::mock(BlockchainProofService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $service->shouldReceive('assertReady')
            ->once()
            ->with('sepolia');

        $service->shouldReceive('findDocumentFile')
            ->once()
            ->with(42)
            ->andReturn($file);

        $service->shouldReceive('prepareDocumentProof')
            ->once()
            ->with($file)
            ->andReturn([
                'proof_type' => 'document',
                'proof_version' => 1,
                'document_file_id' => 42,
                'document_uuid' => 'test-document-uuid',
                'proof_hash' => str_repeat('c', 64),
            ]);

        try {
            $service->validateSepoliaAnchorForSubmission($anchor);
            $this->fail('Expected a source proof mismatch exception.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Source proof hash mismatch. No blockchain transaction was submitted.',
                $exception->getMessage()
            );
        }

        $anchor->refresh();

        $this->assertSame(
            BlockchainAnchor::STATUS_MISMATCH,
            $anchor->status
        );
        $this->assertSame(
            'The source record no longer matches the stored proof hash.',
            $anchor->last_error
        );
        $this->assertNull($anchor->transaction_hash);
        $this->assertSame(0, $anchor->attempts);
    }

    public function test_parses_valid_sepolia_rpc_quantities(): void
    {
        $service = new BlockchainProofService();

        $method = new \ReflectionMethod(
            BlockchainProofService::class,
            'parseSepoliaRpcQuantity'
        );

        $this->assertSame(0, $method->invoke($service, '0x0', 'test'));
        $this->assertSame(21000, $method->invoke($service, '0x5208', 'test'));
        $this->assertSame(255, $method->invoke($service, '0xff', 'test'));
    }

    public function test_rejects_invalid_sepolia_rpc_quantities(): void
    {
        $service = new BlockchainProofService();

        $method = new \ReflectionMethod(
            BlockchainProofService::class,
            'parseSepoliaRpcQuantity'
        );

        foreach ([
            '',
            '0x',
            '5208',
            '0x00',
            '0x-1',
            '0xgg',
            21000,
            null,
        ] as $invalidValue) {
            try {
                $method->invoke($service, $invalidValue, 'test');
                $this->fail(
                    'Expected invalid RPC quantity to be rejected: ' .
                    var_export($invalidValue, true)
                );
            } catch (\ReflectionException $exception) {
                throw $exception;
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString(
                    'invalid test quantity',
                    strtolower($exception->getMessage())
                );
            }
        }
    }

    public function test_prepares_sepolia_transaction_from_mocked_rpc_responses(): void
    {
        config(['blockchain.enabled' => true]);

        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::sequence()
                ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x2'])
                ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x5208'])
                ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x3b9aca00'])
                ->push([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => [
                        'baseFeePerGas' => [
                            '0x3b9aca00',
                            '0x3b9aca00',
                        ],
                    ],
                ]),
        ]);

        $service = new BlockchainProofService();

        $method = new \ReflectionMethod(
            BlockchainProofService::class,
            'prepareSepoliaTransaction'
        );

        $data = '0xc3e6a0dc'
            . str_repeat('a', 64)
            . str_repeat('b', 64)
            . str_pad('1', 64, '0', STR_PAD_LEFT);

        $transaction = $method->invoke(
            $service,
            '0x181432526774881C0b66058A73E771D957f45193',
            '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD',
            $data
        );

        $this->assertSame('0x2', $transaction['nonce']);
        $this->assertSame('0x6270', $transaction['gas']);
        $this->assertSame('0x3b9aca00', $transaction['maxPriorityFeePerGas']);
        $this->assertSame('0xb2d05e00', $transaction['maxFeePerGas']);
        $this->assertSame('0x0', $transaction['value']);
        $this->assertSame($data, $transaction['data']);
        $this->assertSame([], $transaction['accessList']);
    }

    public function test_does_not_resubmit_sepolia_anchor_with_existing_transaction_hash(): void
    {
        $transactionHash = '0x' . str_repeat('c', 64);

        $anchor = BlockchainAnchor::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'document_file_id' => null,
            'document_payment_id' => 84,
            'proof_type' => BlockchainAnchor::TYPE_PAYMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('b', 64),
            'network' => 'sepolia',
            'chain_id' => 11155111,
            'contract_address' =>
                '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD',
            'transaction_hash' => $transactionHash,
            'status' => BlockchainAnchor::STATUS_FAILED,
            'attempts' => 1,
        ]);

        \Illuminate\Support\Facades\Http::fake();

        $service = Mockery::mock(BlockchainProofService::class)
            ->makePartial();

        $service->shouldReceive('validateSepoliaAnchorForSubmission')
            ->once()
            ->with(\Mockery::on(
                fn (BlockchainAnchor $candidate) =>
                    $candidate->is($anchor)
            ))
            ->andReturn([
                'anchor_id' => $anchor->id,
                'proof_id' => '0x' . str_repeat('d', 64),
                'proof_hash' => '0x' . str_repeat('b', 64),
                'proof_type' => 2,
                'network' => 'sepolia',
                'chain_id' => 11155111,
                'contract_address' =>
                    '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD',
                'signer_address' =>
                    '0x181432526774881c0b66058a73e771d957f45193',
                'source_proof_valid' => true,
                'transaction_submitted' => false,
            ]);

        $result = $service->submitAnchorOnSepolia($anchor, 'SUBMIT-SEPOLIA-ANCHOR:' . $anchor->getKey());

        $this->assertSame('reconciliation_required', $result['state']);
        $this->assertSame($transactionHash, $result['transaction_hash']);
        $this->assertSame($transactionHash, $anchor->fresh()->transaction_hash);
        $this->assertSame(1, $anchor->fresh()->attempts);

        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_successfully_submits_signed_sepolia_transaction(): void
    {
        $contractAddress =
            '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD';

        // Hardhat's public test-only account key; never use for real funds.
        $privateKey =
            'ac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80';

        $signerAddress =
            '0xf39Fd6e51aad88f6f4ce6ab8827279cffFb92266';

        config([
            'blockchain.enabled' => true,
            'blockchain.sepolia.rpc_url' => 'https://sepolia-test.example',
            'blockchain.sepolia.chain_id' => 11155111,
            'blockchain.sepolia.contract_address' => $contractAddress,
            'blockchain.sepolia.signer_address' => $signerAddress,
            'blockchain.sepolia.private_key' => $privateKey,
        ]);

        $anchor = BlockchainAnchor::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'document_file_id' => null,
            'document_payment_id' => 84,
            'proof_type' => BlockchainAnchor::TYPE_PAYMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('b', 64),
            'network' => 'sepolia',
            'chain_id' => 11155111,
            'contract_address' => $contractAddress,
            'transaction_hash' => null,
            'status' => BlockchainAnchor::STATUS_PENDING,
            'attempts' => 0,
        ]);

        $service = Mockery::mock(BlockchainProofService::class)
            ->makePartial();

        $service->shouldReceive('validateSepoliaAnchorForSubmission')
            ->once()
            ->with(\Mockery::on(
                fn (BlockchainAnchor $candidate) =>
                    $candidate->is($anchor)
            ))
            ->andReturn([
                'anchor_id' => $anchor->id,
                'proof_id' => '0x' . str_repeat('d', 64),
                'proof_hash' => '0x' . str_repeat('b', 64),
                'proof_type' => 2,
                'network' => 'sepolia',
                'chain_id' => 11155111,
                'contract_address' => $contractAddress,
                'signer_address' => strtolower($signerAddress),
                'source_proof_valid' => true,
                'transaction_submitted' => false,
            ]);

        $service->shouldReceive('getProof')
            ->once()
            ->with('0x' . str_repeat('d', 64), 'sepolia')
            ->andReturn([
                'proof_id' => '0x' . str_repeat('d', 64),
                'proof_hash' => '0x' . str_repeat('b', 64),
                'proof_type' => 0,
                'anchored_at' => 0,
                'anchored_by' => '0x' . str_repeat('0', 40),
                'exists' => false,
            ]);

        $rpcMethods = [];
        $broadcastHash = null;

        \Illuminate\Support\Facades\Http::fake(
            function (
                \Illuminate\Http\Client\Request $request,
                array $options
            ) use (&$rpcMethods, &$broadcastHash) {
                $body = $request->data();
                $method = $body['method'] ?? '';
                $rpcMethods[] = $method;

                $result = match ($method) {
                    'eth_chainId' => '0xaa36a7',
                    'eth_getTransactionCount' => '0x2',
                    'eth_estimateGas' => '0x5208',
                    'eth_maxPriorityFeePerGas' => '0x3b9aca00',
                    'eth_feeHistory' => [
                        'baseFeePerGas' => [
                            '0x3b9aca00',
                            '0x3b9aca00',
                        ],
                    ],
                    'eth_sendRawTransaction' => (function () use (
                        $body,
                        &$broadcastHash
                    ) {
                        $raw = $body['params'][0] ?? '';

                        if (
                            ! is_string($raw)
                            || ! preg_match('/^0x02(?:[a-fA-F0-9]{2})+$/', $raw)
                        ) {
                            throw new \RuntimeException(
                                'Expected a signed EIP-1559 transaction.'
                            );
                        }

                        $bytes = hex2bin(substr($raw, 2));

                        if ($bytes === false) {
                            throw new \RuntimeException(
                                'Invalid raw transaction encoding.'
                            );
                        }

                        $broadcastHash = '0x' . strtolower(
                            (new \Web3p\EthereumUtil\Util())->sha3($bytes)
                        );

                        return $broadcastHash;
                    })(),
                    default => throw new \RuntimeException(
                        'Unexpected RPC method: ' . $method
                    ),
                };

                return \Illuminate\Support\Facades\Http::response([
                    'jsonrpc' => '2.0',
                    'id' => $body['id'] ?? 1,
                    'result' => $result,
                ]);
            }
        );

        $result = $service->submitAnchorOnSepolia($anchor, 'SUBMIT-SEPOLIA-ANCHOR:' . $anchor->getKey());

        $savedAnchor = $anchor->fresh();

        $this->assertNull($savedAnchor->submission_claim_token);
        $this->assertNull($savedAnchor->submission_claimed_at);
        $this->assertSame('submitted', $result['state']);
        $this->assertSame($broadcastHash, $result['transaction_hash']);
        $this->assertSame($broadcastHash, $savedAnchor->transaction_hash);
        $this->assertSame(
            BlockchainAnchor::STATUS_SUBMITTED,
            $savedAnchor->status
        );
        $this->assertSame(1, $savedAnchor->attempts);
        $this->assertNull($savedAnchor->last_error);

        $this->assertSame([
            'eth_getTransactionCount',
            'eth_estimateGas',
            'eth_maxPriorityFeePerGas',
            'eth_feeHistory',
            'eth_sendRawTransaction',
        ], $rpcMethods);
    }

    public function test_preserves_transaction_hash_when_sepolia_broadcast_is_uncertain(): void
    {
        $contractAddress =
            '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD';

        $privateKey =
            'ac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80';

        $signerAddress =
            '0xf39Fd6e51aad88f6f4ce6ab8827279cffFb92266';

        config([
            'blockchain.enabled' => true,
            'blockchain.sepolia.rpc_url' => 'https://sepolia-test.example',
            'blockchain.sepolia.chain_id' => 11155111,
            'blockchain.sepolia.contract_address' => $contractAddress,
            'blockchain.sepolia.signer_address' => $signerAddress,
            'blockchain.sepolia.private_key' => $privateKey,
        ]);

        $anchor = BlockchainAnchor::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'document_file_id' => null,
            'document_payment_id' => 85,
            'proof_type' => BlockchainAnchor::TYPE_PAYMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('b', 64),
            'network' => 'sepolia',
            'chain_id' => 11155111,
            'contract_address' => $contractAddress,
            'transaction_hash' => null,
            'status' => BlockchainAnchor::STATUS_PENDING,
            'attempts' => 0,
        ]);

        $service = Mockery::mock(BlockchainProofService::class)
            ->makePartial();

        $service->shouldReceive('validateSepoliaAnchorForSubmission')
            ->once()
            ->with(\Mockery::on(
                fn (BlockchainAnchor $candidate) =>
                    $candidate->is($anchor)
            ))
            ->andReturn([
                'anchor_id' => $anchor->id,
                'proof_id' => '0x' . str_repeat('d', 64),
                'proof_hash' => '0x' . str_repeat('b', 64),
                'proof_type' => 2,
                'network' => 'sepolia',
                'chain_id' => 11155111,
                'contract_address' => $contractAddress,
                'signer_address' => strtolower($signerAddress),
                'source_proof_valid' => true,
                'transaction_submitted' => false,
            ]);

        $service->shouldReceive('getProof')
            ->once()
            ->with('0x' . str_repeat('d', 64), 'sepolia')
            ->andReturn([
                'proof_id' => '0x' . str_repeat('d', 64),
                'proof_hash' => '0x' . str_repeat('b', 64),
                'proof_type' => 0,
                'anchored_at' => 0,
                'anchored_by' => '0x' . str_repeat('0', 40),
                'exists' => false,
            ]);

        $rpcMethods = [];
        $locallyCalculatedHash = null;

        \Illuminate\Support\Facades\Http::fake(
            function (
                \Illuminate\Http\Client\Request $request,
                array $options
            ) use (&$rpcMethods, &$locallyCalculatedHash) {
                $body = $request->data();
                $method = $body['method'] ?? '';
                $rpcMethods[] = $method;

                $result = match ($method) {
                    'eth_chainId' => '0xaa36a7',
                    'eth_getTransactionCount' => '0x2',
                    'eth_estimateGas' => '0x5208',
                    'eth_maxPriorityFeePerGas' => '0x3b9aca00',
                    'eth_feeHistory' => [
                        'baseFeePerGas' => [
                            '0x3b9aca00',
                            '0x3b9aca00',
                        ],
                    ],
                    'eth_sendRawTransaction' => (function () use (
                        $body,
                        &$locallyCalculatedHash
                    ) {
                        $raw = $body['params'][0] ?? '';

                        if (
                            ! is_string($raw)
                            || ! preg_match('/^0x02(?:[a-fA-F0-9]{2})+$/', $raw)
                        ) {
                            throw new \RuntimeException(
                                'Expected a signed EIP-1559 transaction.'
                            );
                        }

                        $bytes = hex2bin(substr($raw, 2));

                        if ($bytes === false) {
                            throw new \RuntimeException(
                                'Invalid raw transaction encoding.'
                            );
                        }

                        $locallyCalculatedHash = '0x' . strtolower(
                            (new \Web3p\EthereumUtil\Util())->sha3($bytes)
                        );

                        throw new \Illuminate\Http\Client\ConnectionException(
                            'Simulated connection loss during broadcast.'
                        );
                    })(),
                    default => throw new \RuntimeException(
                        'Unexpected RPC method: ' . $method
                    ),
                };

                return \Illuminate\Support\Facades\Http::response([
                    'jsonrpc' => '2.0',
                    'id' => $body['id'] ?? 1,
                    'result' => $result,
                ]);
            }
        );

        $result = $service->submitAnchorOnSepolia($anchor, 'SUBMIT-SEPOLIA-ANCHOR:' . $anchor->getKey());

        $savedAnchor = $anchor->fresh();

        $this->assertNull($savedAnchor->submission_claim_token);
        $this->assertNull($savedAnchor->submission_claimed_at);
        $this->assertSame('reconciliation_required', $result['state']);
        $this->assertSame(
            $locallyCalculatedHash,
            $result['transaction_hash']
        );
        $this->assertSame(
            $locallyCalculatedHash,
            $savedAnchor->transaction_hash
        );
        $this->assertSame(
            BlockchainAnchor::STATUS_SUBMITTED,
            $savedAnchor->status
        );
        $this->assertSame(1, $savedAnchor->attempts);
        $this->assertNotEmpty($savedAnchor->last_error);

        $this->assertSame([
            'eth_getTransactionCount',
            'eth_estimateGas',
            'eth_maxPriorityFeePerGas',
            'eth_feeHistory',
            'eth_sendRawTransaction',
        ], $rpcMethods);
    }

    public function test_rejects_sepolia_transaction_when_signer_does_not_match_private_key(): void
    {
        $contractAddress =
            '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD';

        // Public Hardhat test-only private key.
        $privateKey =
            'ac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80';

        // Deliberately incorrect address for that private key.
        $incorrectSignerAddress =
            '0x181432526774881C0b66058A73E771D957f45193';

        config([
            'blockchain.enabled' => true,
            'blockchain.sepolia.rpc_url' => 'https://sepolia-test.example',
            'blockchain.sepolia.chain_id' => 11155111,
            'blockchain.sepolia.contract_address' => $contractAddress,
            'blockchain.sepolia.signer_address' => $incorrectSignerAddress,
            'blockchain.sepolia.private_key' => $privateKey,
        ]);

        $anchor = BlockchainAnchor::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'document_file_id' => null,
            'document_payment_id' => 86,
            'proof_type' => BlockchainAnchor::TYPE_PAYMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('b', 64),
            'network' => 'sepolia',
            'chain_id' => 11155111,
            'contract_address' => $contractAddress,
            'transaction_hash' => null,
            'status' => BlockchainAnchor::STATUS_PENDING,
            'attempts' => 0,
        ]);

        $service = Mockery::mock(BlockchainProofService::class)
            ->makePartial();

        $service->shouldReceive('validateSepoliaAnchorForSubmission')
            ->once()
            ->with(\Mockery::on(
                fn (BlockchainAnchor $candidate) =>
                    $candidate->is($anchor)
            ))
            ->andReturn([
                'anchor_id' => $anchor->id,
                'proof_id' => '0x' . str_repeat('d', 64),
                'proof_hash' => '0x' . str_repeat('b', 64),
                'proof_type' => 2,
                'network' => 'sepolia',
                'chain_id' => 11155111,
                'contract_address' => $contractAddress,
                'signer_address' => strtolower($incorrectSignerAddress),
                'source_proof_valid' => true,
                'transaction_submitted' => false,
            ]);

        $service->shouldReceive('getProof')
            ->once()
            ->with('0x' . str_repeat('d', 64), 'sepolia')
            ->andReturn([
                'proof_id' => '0x' . str_repeat('d', 64),
                'proof_hash' => '0x' . str_repeat('b', 64),
                'proof_type' => 0,
                'anchored_at' => 0,
                'anchored_by' => '0x' . str_repeat('0', 40),
                'exists' => false,
            ]);

        $rpcMethods = [];

        \Illuminate\Support\Facades\Http::fake(
            function (
                \Illuminate\Http\Client\Request $request,
                array $options
            ) use (&$rpcMethods) {
                $body = $request->data();
                $method = $body['method'] ?? '';
                $rpcMethods[] = $method;

                $result = match ($method) {
                    'eth_chainId' => '0xaa36a7',
                    'eth_getTransactionCount' => '0x2',
                    'eth_estimateGas' => '0x5208',
                    'eth_maxPriorityFeePerGas' => '0x3b9aca00',
                    'eth_feeHistory' => [
                        'baseFeePerGas' => [
                            '0x3b9aca00',
                            '0x3b9aca00',
                        ],
                    ],
                    'eth_sendRawTransaction' => throw new \RuntimeException(
                        'The transaction must not be broadcast.'
                    ),
                    default => throw new \RuntimeException(
                        'Unexpected RPC method: ' . $method
                    ),
                };

                return \Illuminate\Support\Facades\Http::response([
                    'jsonrpc' => '2.0',
                    'id' => $body['id'] ?? 1,
                    'result' => $result,
                ]);
            }
        );

        try {
            $service->submitAnchorOnSepolia($anchor, 'SUBMIT-SEPOLIA-ANCHOR:' . $anchor->getKey());
            $this->fail('Expected a signer-mismatch exception.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'The signing key does not match the configured Sepolia signer.',
                $exception->getMessage()
            );
        }

        $savedAnchor = $anchor->fresh();

        $this->assertNull($savedAnchor->transaction_hash);
        $this->assertSame(BlockchainAnchor::STATUS_PENDING, $savedAnchor->status);
        $this->assertSame(0, $savedAnchor->attempts);

        $this->assertNotContains('eth_sendRawTransaction', $rpcMethods);
    }

    public function test_mismatch_anchor_is_not_reported_as_submission_in_progress(): void
    {
        $anchor = BlockchainAnchor::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'document_file_id' => null,
            'document_payment_id' => 88,
            'proof_type' => BlockchainAnchor::TYPE_PAYMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('b', 64),
            'network' => 'sepolia',
            'chain_id' => 11155111,
            'contract_address' =>
                '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD',
            'transaction_hash' => null,
            'status' => BlockchainAnchor::STATUS_MISMATCH,
            'attempts' => 0,
        ]);

        \Illuminate\Support\Facades\Http::fake();

        $service = new BlockchainProofService();

        try {
            $result = $service->submitAnchorOnSepolia($anchor, 'SUBMIT-SEPOLIA-ANCHOR:' . $anchor->getKey());

            $this->fail(
                'Expected a mismatch-status validation exception, got state: ' .
                ($result['state'] ?? 'unknown')
            );
        } catch (\RuntimeException $exception) {
            $this->assertStringNotContainsString(
                'submission_in_progress',
                $exception->getMessage()
            );
        }

        $savedAnchor = $anchor->fresh();

        $this->assertSame(
            BlockchainAnchor::STATUS_MISMATCH,
            $savedAnchor->status
        );
        $this->assertNull($savedAnchor->transaction_hash);
        $this->assertSame(0, $savedAnchor->attempts);

        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_does_not_submit_anchor_already_claimed_by_another_worker(): void
    {
        $anchor = BlockchainAnchor::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'document_file_id' => null,
            'document_payment_id' => 87,
            'proof_type' => BlockchainAnchor::TYPE_PAYMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('b', 64),
            'network' => 'sepolia',
            'chain_id' => 11155111,
            'contract_address' =>
                '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD',
            'transaction_hash' => null,
            'status' => BlockchainAnchor::STATUS_PENDING,
            'attempts' => 0,
            'submission_claim_token' => (string) \Illuminate\Support\Str::uuid(),
            'submission_claimed_at' => now(),
        ]);

        \Illuminate\Support\Facades\Http::fake();

        $service = new BlockchainProofService();

        $result = $service->submitAnchorOnSepolia($anchor, 'SUBMIT-SEPOLIA-ANCHOR:' . $anchor->getKey());

        $this->assertSame('submission_in_progress', $result['state']);
        $this->assertNull($result['transaction_hash']);

        $savedAnchor = $anchor->fresh();

        $this->assertNull($savedAnchor->transaction_hash);
        $this->assertSame(BlockchainAnchor::STATUS_PENDING, $savedAnchor->status);
        $this->assertSame(0, $savedAnchor->attempts);
        $this->assertNotEmpty($savedAnchor->submission_claim_token);

        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_sepolia_document_anchor_creation_is_rejected_when_blockchain_is_disabled(): void
    {
        config(['blockchain.enabled' => false]);

        $file = new DocumentFile();
        $file->id = 42;

        $service = Mockery::mock(BlockchainProofService::class)
            ->makePartial();

        $service->shouldNotReceive('prepareDocumentProof');

        try {
            $service->createSepoliaDocumentAnchor($file);
            $this->fail('Expected anchor creation to be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertSame(
                'Blockchain integration is disabled. Anchor creation is not allowed.',
                $e->getMessage()
            );
        }

        $this->assertDatabaseCount('blockchain_anchors', 0);
    }

    public function test_sepolia_submission_requires_anchor_specific_operator_confirmation(): void
    {
        config([
            'blockchain.enabled' => true,
            'blockchain.network' => 'sepolia',
        ]);

        $anchor = BlockchainAnchor::query()->create([
            'document_file_id' => 999,
            'document_payment_id' => null,
            'proof_type' => BlockchainAnchor::TYPE_DOCUMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('b', 64),
            'network' => 'sepolia',
            'chain_id' => 11155111,
            'contract_address' =>
                '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD',
            'transaction_hash' => null,
            'status' => BlockchainAnchor::STATUS_PENDING,
            'attempts' => 0,
        ]);

        \Illuminate\Support\Facades\Http::fake();

        $service = new BlockchainProofService();

        try {
            $service->submitAnchorOnSepolia(
                $anchor,
                'SUBMIT-SEPOLIA-ANCHOR:wrong'
            );

            $this->fail(
                'Expected explicit operator confirmation to be required.'
            );
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Explicit operator confirmation for this anchor is required.',
                $exception->getMessage()
            );
        }

        $savedAnchor = $anchor->fresh();

        $this->assertNull($savedAnchor->transaction_hash);
        $this->assertSame(
            BlockchainAnchor::STATUS_PENDING,
            $savedAnchor->status
        );
        $this->assertSame(0, $savedAnchor->attempts);

        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

}
