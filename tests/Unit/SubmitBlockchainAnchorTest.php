<?php

namespace Tests\Unit;

use App\Jobs\ReconcileBlockchainAnchor;
use App\Jobs\SubmitBlockchainAnchor;
use App\Models\BlockchainAnchor;
use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SubmitBlockchainAnchorTest extends TestCase
{
    private const CONTRACT =
        '0x5FbDB2315678afecb367f032d93F642f64180aa3';

    private function createAnchorsTable(): void
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

    private function configureLocalHardhat(): void
    {
        $this->app['env'] = 'local';

        config([
            'app.env' => 'local',
            'blockchain.enabled' => true,
            'blockchain.network' => 'hardhat-local',
            'blockchain.chain_id' => 31337,
            'blockchain.contract_address' => self::CONTRACT,
        ]);
    }

    private function insertAnchor(
        int $id = 1,
        string $status = BlockchainAnchor::STATUS_PENDING,
        ?string $transactionHash = null,
        string $network = 'hardhat-local'
    ): BlockchainAnchor {
        $now = now();

        DB::table('blockchain_anchors')->insert([
            'id' => $id,
            'uuid' => sprintf(
                '11111111-1111-4111-8111-%012d',
                $id
            ),
            'document_file_id' => 999,
            'document_payment_id' => null,
            'proof_type' => BlockchainAnchor::TYPE_DOCUMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('a', 64),
            'network' => $network,
            'chain_id' => 31337,
            'contract_address' => self::CONTRACT,
            'transaction_hash' => $transactionHash,
            'status' => $status,
            'attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return BlockchainAnchor::findOrFail($id);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAnchorsTable();
        $this->configureLocalHardhat();
    }

    public function test_job_is_unique_per_anchor(): void
    {
        $first = new SubmitBlockchainAnchor(10);
        $same = new SubmitBlockchainAnchor(10);
        $different = new SubmitBlockchainAnchor(11);

        $this->assertSame($first->uniqueId(), $same->uniqueId());
        $this->assertNotSame($first->uniqueId(), $different->uniqueId());
        $this->assertSame(300, $first->uniqueFor);
    }

    public function test_missing_anchor_is_ignored(): void
    {
        $blockchain = Mockery::mock(BlockchainProofService::class);
        $blockchain->shouldNotReceive('submitAnchorOnLocalHardhat');

        (new SubmitBlockchainAnchor(999))->handle($blockchain);

        $this->assertDatabaseMissing('blockchain_anchors', ['id' => 999]);
    }

    public function test_job_rejects_non_local_application_environment(): void
    {
        $this->insertAnchor();
        $this->app['env'] = 'testing';

        $blockchain = Mockery::mock(BlockchainProofService::class);
        $blockchain->shouldNotReceive('submitAnchorOnLocalHardhat');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Local Hardhat submission is permitted only'
        );

        (new SubmitBlockchainAnchor(1))->handle($blockchain);
    }

    public function test_job_rejects_non_local_blockchain_network(): void
    {
        $this->insertAnchor();

        config(['blockchain.network' => 'sepolia']);

        $blockchain = Mockery::mock(BlockchainProofService::class);
        $blockchain->shouldNotReceive('submitAnchorOnLocalHardhat');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Local Hardhat submission is permitted only'
        );

        (new SubmitBlockchainAnchor(1))->handle($blockchain);
    }

    public function test_confirmed_anchor_is_not_submitted_again(): void
    {
        $this->insertAnchor(
            status: BlockchainAnchor::STATUS_CONFIRMED
        );

        $blockchain = Mockery::mock(BlockchainProofService::class);
        $blockchain->shouldNotReceive('submitAnchorOnLocalHardhat');

        (new SubmitBlockchainAnchor(1))->handle($blockchain);

        $this->assertDatabaseHas('blockchain_anchors', [
            'id' => 1,
            'status' => BlockchainAnchor::STATUS_CONFIRMED,
        ]);
    }

    public function test_existing_submitted_transaction_is_reconciled_not_resubmitted(): void
    {
        $hash = '0x' . str_repeat('b', 64);

        $this->insertAnchor(
            status: BlockchainAnchor::STATUS_SUBMITTED,
            transactionHash: $hash
        );

        Queue::fake();

        $blockchain = Mockery::mock(BlockchainProofService::class);
        $blockchain->shouldNotReceive('submitAnchorOnLocalHardhat');

        (new SubmitBlockchainAnchor(1))->handle($blockchain);

        Queue::assertPushed(
            ReconcileBlockchainAnchor::class,
            fn (ReconcileBlockchainAnchor $job): bool =>
                $job->anchorId === 1
        );
    }

    public function test_successful_submission_dispatches_reconciliation(): void
    {
        $this->insertAnchor();
        Queue::fake();

        $hash = '0x' . str_repeat('c', 64);

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldReceive('submitAnchorOnLocalHardhat')
            ->once()
            ->andReturnUsing(function (BlockchainAnchor $anchor) use ($hash): array {
                DB::table('blockchain_anchors')
                    ->where('id', $anchor->id)
                    ->update([
                        'status' => BlockchainAnchor::STATUS_SUBMITTED,
                        'transaction_hash' => $hash,
                        'updated_at' => now(),
                    ]);

                return [
                    'state' => 'submitted',
                    'transaction_hash' => $hash,
                ];
            });

        (new SubmitBlockchainAnchor(1))->handle($blockchain);

        $this->assertDatabaseHas('blockchain_anchors', [
            'id' => 1,
            'status' => BlockchainAnchor::STATUS_SUBMITTED,
            'transaction_hash' => $hash,
        ]);

        Queue::assertPushed(
            ReconcileBlockchainAnchor::class,
            fn (ReconcileBlockchainAnchor $job): bool =>
                $job->anchorId === 1
        );
    }

    public function test_uncertain_submission_without_saved_transaction_hash_is_not_retried_inside_job(): void
    {
        $this->insertAnchor();

        Queue::fake();

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldReceive('submitAnchorOnLocalHardhat')
            ->once()
            ->andReturn([
                'state' => 'reconciliation_required',
                'transaction_hash' => null,
            ]);

        (new SubmitBlockchainAnchor(1))->handle($blockchain);

        $this->assertDatabaseHas('blockchain_anchors', [
            'id' => 1,
            'status' => BlockchainAnchor::STATUS_PENDING,
            'transaction_hash' => null,
        ]);

        Queue::assertNothingPushed();
    }
}