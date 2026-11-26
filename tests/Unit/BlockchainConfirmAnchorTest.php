<?php

namespace Tests\Unit;

use App\Models\BlockchainAnchor;
use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class BlockchainConfirmAnchorTest extends TestCase
{
    private function createBlockchainAnchorsTable(): void
    {
        Schema::create('blockchain_anchors', function (Blueprint $table): void {
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
        });
    }

    public function test_missing_receipt_leaves_submitted_anchor_unchanged(): void
    {
        config([
            'blockchain.enabled' => true,
            'blockchain.network' => 'hardhat-local',
            'blockchain.chain_id' => 31337,
            'blockchain.contract_address' =>
                '0x5FbDB2315678afecb367f032d93F642f64180aa3',
        ]);

        $anchor = new BlockchainAnchor([
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'document_file_id' => 999,
            'proof_type' => BlockchainAnchor::TYPE_DOCUMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('a', 64),
            'network' => 'hardhat-local',
            'chain_id' => 31337,
            'contract_address' =>
                '0x5FbDB2315678afecb367f032d93F642f64180aa3',
            'transaction_hash' => '0x' . str_repeat('b', 64),
            'status' => BlockchainAnchor::STATUS_SUBMITTED,
            'attempts' => 1,
        ]);

        // Simulate an existing database record without persisting it.
        $anchor->exists = true;
        $originalTransactionHash = $anchor->transaction_hash;

        $service = Mockery::mock(
            BlockchainProofService::class
        )->makePartial()->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('assertReady')
            ->once();

        $service->shouldReceive('getChainId')
            ->once()
            ->andReturn(31337);

        $service->shouldReceive('getTransactionReceipt')
            ->once()
            ->with($originalTransactionHash, 'hardhat-local')
            ->andReturn(null);

        $result = $service->confirmAnchor($anchor);

        $this->assertSame('pending_receipt', $result['state']);
        $this->assertNull($result['anchor_id']);
        $this->assertSame(
            BlockchainAnchor::STATUS_SUBMITTED,
            $anchor->status
        );
        $this->assertSame(
            $originalTransactionHash,
            $anchor->transaction_hash
        );
        $this->assertNull($anchor->confirmed_at);
    }

    public function test_matching_receipt_and_proof_confirms_anchor(): void
    {
        config([
            'blockchain.enabled' => true,
            'blockchain.network' => 'hardhat-local',
            'blockchain.chain_id' => 31337,
            'blockchain.contract_address' =>
                '0x5FbDB2315678afecb367f032d93F642f64180aa3',
        ]);

        $anchor = new class([
            'uuid' => '22222222-2222-4222-8222-222222222222',
            'document_file_id' => 999,
            'proof_type' => BlockchainAnchor::TYPE_DOCUMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('a', 64),
            'network' => 'hardhat-local',
            'chain_id' => 31337,
            'contract_address' =>
                '0x5FbDB2315678afecb367f032d93F642f64180aa3',
            'transaction_hash' => '0x' . str_repeat('b', 64),
            'status' => BlockchainAnchor::STATUS_SUBMITTED,
            'attempts' => 1,
        ]) extends BlockchainAnchor {
            public function save(array $options = []): bool
            {
                return true;
            }
        };

        $anchor->exists = true;
        $anchor->id = 123;

        $service = Mockery::mock(
            BlockchainProofService::class
        )->makePartial()->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('assertReady')->once();
        $service->shouldReceive('getChainId')->once()->andReturn(31337);

        $service->shouldReceive('getTransactionReceipt')
            ->once()
            ->with($anchor->transaction_hash, 'hardhat-local')
            ->andReturn([
                'transactionHash' => $anchor->transaction_hash,
                'to' => $anchor->contract_address,
                'status' => '0x1',
                'blockNumber' => '0x5',
            ]);

        $service->shouldReceive('inspectAnchorOnChain')
            ->once()
            ->with($anchor)
            ->andReturn([
                'anchor_id' => 123,
                'proof_id' => '0x' . str_repeat('c', 64),
                'state' => 'matching',
                'on_chain_proof' => ['exists' => true],
            ]);

        $service->shouldReceive('validateProofAnchoredEvent')
            ->once()
            ->with(Mockery::type('array'), $anchor)
            ->andReturn(true);

        $result = $service->confirmAnchor($anchor);

        $this->assertSame('confirmed', $result['state']);
        $this->assertSame(5, $result['block_number']);
        $this->assertSame(
            BlockchainAnchor::STATUS_CONFIRMED,
            $anchor->status
        );
        $this->assertNull($anchor->last_error);
        $this->assertNotNull($anchor->confirmed_at);
    }

    public function test_mismatching_on_chain_proof_marks_anchor_as_mismatch(): void
    {
        config([
            'blockchain.enabled' => true,
            'blockchain.network' => 'hardhat-local',
            'blockchain.chain_id' => 31337,
            'blockchain.contract_address' =>
                '0x5FbDB2315678afecb367f032d93F642f64180aa3',
        ]);

        $anchor = new class([
            'uuid' => '33333333-3333-4333-8333-333333333333',
            'document_file_id' => 999,
            'proof_type' => BlockchainAnchor::TYPE_DOCUMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('a', 64),
            'network' => 'hardhat-local',
            'chain_id' => 31337,
            'contract_address' =>
                '0x5FbDB2315678afecb367f032d93F642f64180aa3',
            'transaction_hash' => '0x' . str_repeat('b', 64),
            'status' => BlockchainAnchor::STATUS_SUBMITTED,
            'attempts' => 1,
        ]) extends BlockchainAnchor {
            public function save(array $options = []): bool
            {
                return true;
            }
        };

        $anchor->exists = true;
        $anchor->id = 124;

        $service = Mockery::mock(
            BlockchainProofService::class
        )->makePartial()->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('assertReady')->once();
        $service->shouldReceive('getChainId')->once()->andReturn(31337);

        $service->shouldReceive('getTransactionReceipt')
            ->once()
            ->with($anchor->transaction_hash, 'hardhat-local')
            ->andReturn([
                'transactionHash' => $anchor->transaction_hash,
                'to' => $anchor->contract_address,
                'status' => '0x1',
                'blockNumber' => '0x6',
            ]);

        $service->shouldReceive('inspectAnchorOnChain')
            ->once()
            ->with($anchor)
            ->andReturn([
                'anchor_id' => 124,
                'proof_id' => '0x' . str_repeat('c', 64),
                'state' => 'mismatch',
                'on_chain_proof' => [
                    'exists' => true,
                    'proofHash' => '0x' . str_repeat('d', 64),
                ],
            ]);

        $service->shouldReceive('validateProofAnchoredEvent')
            ->once()
            ->with(Mockery::type('array'), $anchor)
            ->andReturn(true);

        $result = $service->confirmAnchor($anchor);

        $this->assertSame('proof_mismatch', $result['state']);
        $this->assertSame(
            BlockchainAnchor::STATUS_MISMATCH,
            $anchor->status
        );
        $this->assertNotNull($anchor->last_error);
        $this->assertNull($anchor->confirmed_at);
    }

    public function test_failed_transaction_receipt_marks_anchor_as_failed(): void
    {
        config([
            'blockchain.enabled' => true,
            'blockchain.network' => 'hardhat-local',
            'blockchain.chain_id' => 31337,
            'blockchain.contract_address' =>
                '0x5FbDB2315678afecb367f032d93F642f64180aa3',
        ]);

        $anchor = new class([
            'uuid' => '44444444-4444-4444-8444-444444444444',
            'document_file_id' => 999,
            'proof_type' => BlockchainAnchor::TYPE_DOCUMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('a', 64),
            'network' => 'hardhat-local',
            'chain_id' => 31337,
            'contract_address' =>
                '0x5FbDB2315678afecb367f032d93F642f64180aa3',
            'transaction_hash' => '0x' . str_repeat('e', 64),
            'status' => BlockchainAnchor::STATUS_SUBMITTED,
            'attempts' => 1,
        ]) extends BlockchainAnchor {
            public function save(array $options = []): bool
            {
                return true;
            }
        };

        $anchor->exists = true;
        $anchor->id = 125;

        $service = Mockery::mock(
            BlockchainProofService::class
        )->makePartial()->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('assertReady')->once();
        $service->shouldReceive('getChainId')->once()->andReturn(31337);

        $service->shouldReceive('getTransactionReceipt')
            ->once()
            ->with($anchor->transaction_hash, 'hardhat-local')
            ->andReturn([
                'transactionHash' => $anchor->transaction_hash,
                'to' => $anchor->contract_address,
                'status' => '0x0',
                'blockNumber' => '0x7',
            ]);

        $result = $service->confirmAnchor($anchor);

        $this->assertSame('transaction_failed', $result['state']);
        $this->assertSame(
            BlockchainAnchor::STATUS_FAILED,
            $anchor->status
        );
        $this->assertNotNull($anchor->last_error);
        $this->assertNull($anchor->confirmed_at);
    }

    public function test_existing_matching_proof_without_transaction_hash_requires_reconciliation(): void
    {
        config([
            'blockchain.enabled' => true,
            'blockchain.network' => 'hardhat-local',
            'blockchain.chain_id' => 31337,
            'blockchain.rpc_url' => 'http://127.0.0.1:8545',
            'blockchain.signer_address' =>
                '0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266',
            'blockchain.contract_address' =>
                '0x5FbDB2315678afecb367f032d93F642f64180aa3',
        ]);

        $anchor = new BlockchainAnchor([
            'uuid' => '55555555-5555-4555-8555-555555555555',
            'document_file_id' => 999,
            'proof_type' => BlockchainAnchor::TYPE_DOCUMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('a', 64),
            'network' => 'hardhat-local',
            'chain_id' => 31337,
            'contract_address' =>
                '0x5FbDB2315678afecb367f032d93F642f64180aa3',
            'transaction_hash' => null,
            'status' => BlockchainAnchor::STATUS_PENDING,
            'attempts' => 0,
        ]);

        Schema::create('blockchain_anchors', function (Blueprint $table): void {
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
        });

        DB::table('blockchain_anchors')->insert([
            'id' => 126,
            'uuid' => '55555555-5555-4555-8555-555555555555',
            'document_file_id' => 999,
            'document_payment_id' => null,
            'proof_type' => BlockchainAnchor::TYPE_DOCUMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('a', 64),
            'network' => 'hardhat-local',
            'chain_id' => 31337,
            'contract_address' => '0x5FbDB2315678afecb367f032d93F642f64180aa3',
            'transaction_hash' => null,
            'submission_claim_token' => null,
            'submission_claimed_at' => null,
            'block_number' => null,
            'status' => BlockchainAnchor::STATUS_PENDING,
            'attempts' => 0,
            'last_error' => null,
            'submitted_at' => null,
            'confirmed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Use the actual persisted model for the service call.
        $anchor = BlockchainAnchor::findOrFail(126);

        $source = new \App\Models\DocumentFile([
            'id' => 999,
            'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'disk' => 'local',
            'path' => 'test-document.pdf',
            'filename' => 'test-document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'checksum' => str_repeat('a', 64),
        ]);

        $service = Mockery::mock(
            BlockchainProofService::class
        )->makePartial()->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('findDocumentFile')
            ->once()
            ->with(999)
            ->andReturn($source);

        $service->shouldReceive('assertReady')
            ->once();

        $service->shouldReceive('prepareDocumentProof')
            ->once()
            ->with($source)
            ->andReturn([
                'proof_type' => 'document',
                'proof_version' => 1,
                'document_file_id' => 999,
                'document_uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                'proof_hash' => str_repeat('a', 64),
            ]);

        $service->shouldReceive('getProof')
            ->once()
            ->with($service->proofIdForAnchor($anchor))
            ->andReturn([
                'proof_id' => $service->proofIdForAnchor($anchor),
                'proof_hash' => '0x' . str_repeat('a', 64),
                'anchored_at' => 1792225213,
                'proof_type' => 1,
                'anchored_by' =>
                    '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266',
                'exists' => true,
            ]);

        $result = $service->submitAnchorOnLocalHardhat($anchor);

        $this->assertSame('reconciliation_required', $result['state']);
        $this->assertNull($result['transaction_hash']);
        $this->assertSame(
            BlockchainAnchor::STATUS_PENDING,
            $anchor->status
        );
        $this->assertNull($anchor->transaction_hash);
    }

}