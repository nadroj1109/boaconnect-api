<?php

namespace Tests\Unit;

use App\Jobs\AutoSubmitMainnetBlockchainAnchor;
use App\Jobs\ReconcileBlockchainAnchor;
use App\Models\BlockchainAnchor;
use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class AutoSubmitMainnetBlockchainAnchorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'blockchain.enabled' => true,
            'blockchain.network' => 'mainnet',
            'blockchain.mainnet.auto_submit' => false,
        ]);

        Schema::dropIfExists('blockchain_anchors');

        Schema::create('blockchain_anchors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('document_file_id')->nullable();
            $table->unsignedBigInteger('document_payment_id')->nullable();
            $table->string('proof_type', 20);
            $table->unsignedInteger('proof_version')->default(1);
            $table->string('proof_hash', 64);
            $table->string('network', 32);
            $table->unsignedBigInteger('chain_id');
            $table->string('contract_address', 42)->nullable();
            $table->string('transaction_hash', 66)->nullable();
            $table->uuid('submission_claim_token')->nullable();
            $table->timestamp('submission_claimed_at')->nullable();
            $table->unsignedBigInteger('block_number')->nullable();
            $table->string('status', 20)->default('pending');
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

            $table->index(
                ['status', 'created_at'],
                'test_blockchain_anchors_status_created_at_index'
            );

            $table->index(
                'transaction_hash',
                'test_blockchain_anchors_transaction_hash_index'
            );
        });
    }

    private function createAnchor(array $overrides = []): BlockchainAnchor
    {
        return BlockchainAnchor::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'document_file_id' => 1,
            'document_payment_id' => null,
            'proof_type' => BlockchainAnchor::TYPE_DOCUMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('a', 64),
            'network' => 'mainnet',
            'chain_id' => 1,
            'contract_address' => '0x' . str_repeat('1', 40),
            'status' => BlockchainAnchor::STATUS_PENDING,
            'attempts' => 0,
        ], $overrides));
    }

    public function test_job_exits_when_automatic_submission_is_disabled(): void
    {
        $anchor = $this->createAnchor();

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldNotReceive(
            'submitAnchorOnMainnetAutomatically'
        );

        (new AutoSubmitMainnetBlockchainAnchor($anchor->id))
            ->handle($blockchain);

        $this->assertSame(
            BlockchainAnchor::STATUS_PENDING,
            $anchor->fresh()->status
        );
    }

    public function test_job_exits_when_global_network_is_not_mainnet(): void
    {
        config([
            'blockchain.mainnet.auto_submit' => true,
            'blockchain.network' => 'sepolia',
        ]);

        $anchor = $this->createAnchor();

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldNotReceive(
            'submitAnchorOnMainnetAutomatically'
        );

        (new AutoSubmitMainnetBlockchainAnchor($anchor->id))
            ->handle($blockchain);
    }

    public function test_job_submits_eligible_anchor_and_dispatches_reconciliation(): void
    {
        config([
            'blockchain.mainnet.auto_submit' => true,
        ]);

        $anchor = $this->createAnchor();

        Queue::fake();

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain
            ->shouldReceive('submitAnchorOnMainnetAutomatically')
            ->once()
            ->with(Mockery::on(
                fn (BlockchainAnchor $received) =>
                    $received->id === $anchor->id
                    && $received->network === 'mainnet'
                    && (int) $received->chain_id === 1
                    && $received->status === BlockchainAnchor::STATUS_PENDING
            ))
            ->andReturn([
                'anchor_id' => $anchor->id,
                'state' => 'submitted',
                'transaction_hash' => '0x' . str_repeat('b', 64),
            ]);

        (new AutoSubmitMainnetBlockchainAnchor($anchor->id))
            ->handle($blockchain);

        Queue::assertPushed(
            ReconcileBlockchainAnchor::class,
            fn (ReconcileBlockchainAnchor $queued) =>
                $queued->anchorId === $anchor->id
        );
    }

    public function test_job_does_not_submit_anchor_with_existing_transaction_hash(): void
    {
        config([
            'blockchain.mainnet.auto_submit' => true,
        ]);

        $anchor = $this->createAnchor([
            'transaction_hash' => '0x' . str_repeat('b', 64),
        ]);

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldNotReceive(
            'submitAnchorOnMainnetAutomatically'
        );

        (new AutoSubmitMainnetBlockchainAnchor($anchor->id))
            ->handle($blockchain);
    }

    public function test_job_does_not_submit_confirmed_anchor(): void
    {
        config([
            'blockchain.mainnet.auto_submit' => true,
        ]);

        $anchor = $this->createAnchor([
            'status' => BlockchainAnchor::STATUS_CONFIRMED,
        ]);

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldNotReceive(
            'submitAnchorOnMainnetAutomatically'
        );

        (new AutoSubmitMainnetBlockchainAnchor($anchor->id))
            ->handle($blockchain);
    }

    public function test_job_does_not_submit_anchor_for_another_network(): void
    {
        config([
            'blockchain.mainnet.auto_submit' => true,
        ]);

        $anchor = $this->createAnchor([
            'network' => 'sepolia',
            'chain_id' => 11155111,
        ]);

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldNotReceive(
            'submitAnchorOnMainnetAutomatically'
        );

        (new AutoSubmitMainnetBlockchainAnchor($anchor->id))
            ->handle($blockchain);
    }

    public function test_job_ignores_missing_anchor(): void
    {
        config([
            'blockchain.mainnet.auto_submit' => true,
        ]);

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldNotReceive(
            'submitAnchorOnMainnetAutomatically'
        );

        (new AutoSubmitMainnetBlockchainAnchor(999999))
            ->handle($blockchain);
    }

    public function test_reconciliation_required_result_does_not_dispatch_another_submission(): void
    {
        config([
            'blockchain.mainnet.auto_submit' => true,
        ]);

        $anchor = $this->createAnchor();

        Queue::fake();

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Automatic Mainnet submission requires reconciliation.',
                [
                    'anchor_id' => $anchor->id,
                    'transaction_hash' => '0x' . str_repeat('c', 64),
                ]
            );

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain
            ->shouldReceive('submitAnchorOnMainnetAutomatically')
            ->once()
            ->andReturn([
                'anchor_id' => $anchor->id,
                'state' => 'reconciliation_required',
                'transaction_hash' => '0x' . str_repeat('c', 64),
            ]);

        (new AutoSubmitMainnetBlockchainAnchor($anchor->id))
            ->handle($blockchain);

        Queue::assertNotPushed(
            ReconcileBlockchainAnchor::class
        );
    }
}
