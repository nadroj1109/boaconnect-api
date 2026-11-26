<?php

namespace Tests\Unit;

use App\Models\BlockchainAnchor;
use App\Models\DocumentFile;
use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MainnetAnchorSubmissionValidationTest extends TestCase
{
    private const CONTRACT_ADDRESS =
        '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD';

    private const SIGNER_ADDRESS =
        '0x181432526774881C0b66058A73E771D957f45193';

    // Public test-only key. Never use this key outside automated tests.
    private const TEST_PRIVATE_KEY =
        'ac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'blockchain.enabled' => true,
            'blockchain.mainnet.chain_id' => 1,
            'blockchain.mainnet.rpc_url' => 'https://mainnet-test.example',
            'blockchain.mainnet.contract_address' =>
                self::CONTRACT_ADDRESS,
            'blockchain.mainnet.signer_address' =>
                self::SIGNER_ADDRESS,
            'blockchain.mainnet.private_key' =>
                self::TEST_PRIVATE_KEY,
            'blockchain.mainnet.auto_submit' => false,
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

        Http::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function makeAnchor(array $overrides = []): BlockchainAnchor
    {
        return BlockchainAnchor::query()->create(array_merge([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'document_file_id' => 42,
            'document_payment_id' => null,
            'proof_type' => BlockchainAnchor::TYPE_DOCUMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('a', 64),
            'network' => 'mainnet',
            'chain_id' => 1,
            'contract_address' => self::CONTRACT_ADDRESS,
            'transaction_hash' => null,
            'status' => BlockchainAnchor::STATUS_PENDING,
            'attempts' => 0,
        ], $overrides));
    }

    private function mockValidSource(
        BlockchainProofService $service,
        BlockchainAnchor $anchor,
        string $currentHash = ''
    ): void {
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
                'proof_hash' => $currentHash !== ''
                    ? $currentHash
                    : (string) $anchor->proof_hash,
            ]);
    }

    private function mockReadinessAndWriter(
        BlockchainProofService $service,
        bool $authorized = true
    ): void {
        $service->shouldReceive('assertReady')
            ->once()
            ->with('mainnet');

        $service->shouldReceive('isWriter')
            ->once()
            ->with(self::SIGNER_ADDRESS, 'mainnet')
            ->andReturn($authorized);
    }

    public function test_validates_a_mainnet_document_anchor_without_broadcasting(): void
    {
        $anchor = $this->makeAnchor();

        $service = Mockery::mock(BlockchainProofService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $this->mockReadinessAndWriter($service);
        $this->mockValidSource($service, $anchor);

        $result = $service->validateMainnetAnchorForSubmission($anchor);

        $this->assertSame($anchor->id, $result['anchor_id']);
        $this->assertSame('mainnet', $result['network']);
        $this->assertSame(1, $result['chain_id']);
        $this->assertSame(
            self::CONTRACT_ADDRESS,
            $result['contract_address']
        );
        $this->assertSame(
            strtolower(self::SIGNER_ADDRESS),
            $result['signer_address']
        );
        $this->assertSame('0x' . str_repeat('a', 64), $result['proof_hash']);
        $this->assertSame(1, $result['proof_type']);
        $this->assertTrue($result['source_proof_valid']);
        $this->assertFalse($result['transaction_submitted']);

        Http::assertNothingSent();
    }

    public function test_rejects_anchor_with_wrong_network_chain_or_contract(): void
    {
        $anchor = $this->makeAnchor([
            'chain_id' => 11155111,
        ]);

        $service = Mockery::mock(BlockchainProofService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $service->shouldReceive('assertReady')
            ->once()
            ->with('mainnet');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The anchor network, chain ID, or contract does not match Mainnet configuration.'
        );

        try {
            $service->validateMainnetAnchorForSubmission($anchor);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_rejects_anchor_that_is_not_pending_or_failed(): void
    {
        $anchor = $this->makeAnchor([
            'status' => BlockchainAnchor::STATUS_CONFIRMED,
        ]);

        $service = Mockery::mock(BlockchainProofService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $service->shouldReceive('assertReady')
            ->once()
            ->with('mainnet');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Only pending or failed Mainnet anchors may be submitted.'
        );

        $service->validateMainnetAnchorForSubmission($anchor);
    }

    public function test_rejects_anchor_that_already_has_transaction_hash(): void
    {
        $anchor = $this->makeAnchor([
            'transaction_hash' => '0x' . str_repeat('b', 64),
        ]);

        $service = Mockery::mock(BlockchainProofService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $service->shouldReceive('assertReady')
            ->once()
            ->with('mainnet');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'This anchor already has a transaction hash and must be reconciled.'
        );

        $service->validateMainnetAnchorForSubmission($anchor);
    }

    public function test_marks_anchor_as_mismatch_when_source_proof_changed(): void
    {
        $anchor = $this->makeAnchor();

        $service = Mockery::mock(BlockchainProofService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $service->shouldReceive('assertReady')
            ->once()
            ->with('mainnet');

        $this->mockValidSource(
            $service,
            $anchor,
            str_repeat('c', 64)
        );

        try {
            $service->validateMainnetAnchorForSubmission($anchor);
            $this->fail('Expected a source proof mismatch exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Source proof hash mismatch. No blockchain transaction was submitted.',
                $exception->getMessage()
            );
        }

        $savedAnchor = $anchor->fresh();

        $this->assertSame(
            BlockchainAnchor::STATUS_MISMATCH,
            $savedAnchor->status
        );
        $this->assertSame(
            'The source record no longer matches the stored proof hash.',
            $savedAnchor->last_error
        );
        $this->assertNull($savedAnchor->transaction_hash);
        $this->assertSame(0, $savedAnchor->attempts);

        Http::assertNothingSent();
    }

    public function test_rejects_unauthorized_mainnet_writer(): void
    {
        $anchor = $this->makeAnchor();

        $service = Mockery::mock(BlockchainProofService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $this->mockReadinessAndWriter($service, false);
        $this->mockValidSource($service, $anchor);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The configured Mainnet signer is not an authorized contract writer.'
        );

        try {
            $service->validateMainnetAnchorForSubmission($anchor);
        } finally {
            Http::assertNothingSent();
        }
    }
}
