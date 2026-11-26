<?php

namespace Tests\Unit;

use App\Jobs\ReconcileBlockchainAnchor;
use App\Models\BlockchainAnchor;
use App\Services\Blockchain\BlockchainProofService;
use App\Console\Commands\ReconcileBlockchainAnchors;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ReconcileBlockchainAnchorTest extends TestCase
{
    public function test_pending_receipt_throws_exception_for_queue_retry(): void
    {
        $anchor = new class([
            'uuid' => '55555555-5555-4555-8555-555555555555',
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
        };

        $anchor->exists = true;
        $anchor->id = 123;

        $job = new class(123, $anchor) extends ReconcileBlockchainAnchor {
            public function __construct(
                int $anchorId,
                private BlockchainAnchor $testAnchor
            ) {
                parent::__construct($anchorId);
            }

            protected function findAnchor(): ?BlockchainAnchor
            {
                return $this->testAnchor;
            }
        };

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldReceive('confirmAnchor')
            ->once()
            ->with($anchor)
            ->andReturn(['state' => 'pending_receipt']);

        try {
            $job->handle($blockchain);
            $this->fail('Expected a retry-triggering exception.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'receipt is not available yet',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            BlockchainAnchor::STATUS_SUBMITTED,
            $anchor->status
        );

        $this->assertSame(5, $job->tries);
        $this->assertSame(15, $job->backoff);
        $this->assertSame(60, $job->timeout);
    }

    public function test_job_completes_when_anchor_is_confirmed(): void
    {
        $anchor = new class([
            'uuid' => '66666666-6666-4666-8666-666666666666',
            'document_file_id' => 999,
            'proof_type' => BlockchainAnchor::TYPE_DOCUMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('c', 64),
            'network' => 'hardhat-local',
            'chain_id' => 31337,
            'contract_address' =>
                '0x5FbDB2315678afecb367f032d93F642f64180aa3',
            'transaction_hash' => '0x' . str_repeat('d', 64),
            'status' => BlockchainAnchor::STATUS_SUBMITTED,
            'attempts' => 1,
        ]) extends BlockchainAnchor {
        };

        $anchor->exists = true;
        $anchor->id = 789;

        $job = new class(789, $anchor) extends ReconcileBlockchainAnchor {
            public function __construct(
                int $anchorId,
                private BlockchainAnchor $testAnchor
            ) {
                parent::__construct($anchorId);
            }

            protected function findAnchor(): ?BlockchainAnchor
            {
                return $this->testAnchor;
            }
        };

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldReceive('confirmAnchor')
            ->once()
            ->with($anchor)
            ->andReturnUsing(function () use ($anchor): array {
                $anchor->status = BlockchainAnchor::STATUS_CONFIRMED;

                return [
                    'anchor_id' => $anchor->id,
                    'state' => 'confirmed',
                    'transaction_hash' => $anchor->transaction_hash,
                    'block_number' => 10,
                ];
            });

        $job->handle($blockchain);

        $this->assertSame(
            BlockchainAnchor::STATUS_CONFIRMED,
            $anchor->status
        );
    }

    public function test_job_is_unique_per_anchor(): void
    {
        $first = new ReconcileBlockchainAnchor(123);
        $sameAnchor = new ReconcileBlockchainAnchor(123);
        $differentAnchor = new ReconcileBlockchainAnchor(456);

        $this->assertSame(
            $first->uniqueId(),
            $sameAnchor->uniqueId()
        );

        $this->assertNotSame(
            $first->uniqueId(),
            $differentAnchor->uniqueId()
        );

        $this->assertSame(300, $first->uniqueFor);
    }

    public function test_recovery_command_rejects_invalid_limit(): void
    {
        $this->artisan('app:reconcile-blockchain-anchors', [
            '--limit' => 0,
        ])
            ->expectsOutput(
                'The --limit option must be a positive integer.'
            )
            ->assertExitCode(1);
    }

    public function test_recovery_command_with_no_eligible_anchors_dispatches_no_jobs(): void
    {
        \Illuminate\Support\Facades\Schema::create(
            'blockchain_anchors',
            function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->id();
                $table->string('status');
                $table->string('transaction_hash')->nullable();
            }
        );

        \Illuminate\Support\Facades\Queue::fake();

        $this->artisan('app:reconcile-blockchain-anchors', [
            '--limit' => 100,
        ])
            ->expectsOutput(
                'Processed 0 eligible anchor(s) for reconciliation.'
            )
            ->assertExitCode(0);

        \Illuminate\Support\Facades\Queue::assertNothingPushed();
    }

    public function test_recovery_command_dispatches_only_eligible_anchors(): void
    {
        \Illuminate\Support\Facades\Schema::create(
            'blockchain_anchors',
            function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->id();
                $table->string('status');
                $table->string('transaction_hash')->nullable();
            }
        );

        \Illuminate\Support\Facades\DB::table('blockchain_anchors')->insert([
            [
                'id' => 1,
                'status' => BlockchainAnchor::STATUS_SUBMITTED,
                'transaction_hash' => '0x' . str_repeat('a', 64),
            ],
            [
                'id' => 2,
                'status' => BlockchainAnchor::STATUS_CONFIRMED,
                'transaction_hash' => '0x' . str_repeat('b', 64),
            ],
            [
                'id' => 3,
                'status' => BlockchainAnchor::STATUS_SUBMITTED,
                'transaction_hash' => null,
            ],
        ]);

        \Illuminate\Support\Facades\Queue::fake();

        $this->artisan('app:reconcile-blockchain-anchors', [
            '--limit' => 100,
        ])
            ->expectsOutput(
                'Processed 1 eligible anchor(s) for reconciliation.'
            )
            ->assertExitCode(0);

        \Illuminate\Support\Facades\Queue::assertPushed(
            ReconcileBlockchainAnchor::class,
            fn (ReconcileBlockchainAnchor $job): bool => $job->anchorId === 1
        );

        \Illuminate\Support\Facades\Queue::assertPushed(
            ReconcileBlockchainAnchor::class,
            fn (ReconcileBlockchainAnchor $job): bool => $job->anchorId === 1,
            1
        );

        \Illuminate\Support\Facades\Queue::assertNotPushed(
            ReconcileBlockchainAnchor::class,
            fn (ReconcileBlockchainAnchor $job): bool => in_array(
                $job->anchorId,
                [2, 3],
                true
            )
        );
    }

    public function test_stale_claim_recovery_releases_only_eligible_claims(): void
    {
        \Illuminate\Support\Facades\Schema::create(
            'blockchain_anchors',
            function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->id();
                $table->string('status');
                $table->string('transaction_hash')->nullable();
                $table->uuid('submission_claim_token')->nullable();
                $table->timestamp('submission_claimed_at')->nullable();
            }
        );

        $stale = now()->subMinutes(30);
        $fresh = now()->subMinutes(5);

        DB::table('blockchain_anchors')->insert([
            [
                'id' => 1,
                'status' => BlockchainAnchor::STATUS_PENDING,
                'transaction_hash' => null,
                'submission_claim_token' => '11111111-1111-4111-8111-111111111111',
                'submission_claimed_at' => $stale,
            ],
            [
                'id' => 2,
                'status' => BlockchainAnchor::STATUS_FAILED,
                'transaction_hash' => null,
                'submission_claim_token' => '22222222-2222-4222-8222-222222222222',
                'submission_claimed_at' => $stale,
            ],
            [
                'id' => 3,
                'status' => BlockchainAnchor::STATUS_PENDING,
                'transaction_hash' => null,
                'submission_claim_token' => '33333333-3333-4333-8333-333333333333',
                'submission_claimed_at' => $fresh,
            ],
            [
                'id' => 4,
                'status' => BlockchainAnchor::STATUS_SUBMITTED,
                'transaction_hash' => '0x' . str_repeat('a', 64),
                'submission_claim_token' => '44444444-4444-4444-8444-444444444444',
                'submission_claimed_at' => $stale,
            ],
            [
                'id' => 5,
                'status' => BlockchainAnchor::STATUS_PENDING,
                'transaction_hash' => '0x' . str_repeat('b', 64),
                'submission_claim_token' => '55555555-5555-4555-8555-555555555555',
                'submission_claimed_at' => $stale,
            ],
            [
                'id' => 6,
                'status' => BlockchainAnchor::STATUS_PENDING,
                'transaction_hash' => null,
                'submission_claim_token' => null,
                'submission_claimed_at' => null,
            ],
        ]);

        $this->artisan('app:release-stale-blockchain-submission-claims', [
            '--minutes' => 15,
            '--limit' => 100,
        ])->assertExitCode(0);

        foreach ([1, 2] as $id) {
            $anchor = DB::table('blockchain_anchors')->where('id', $id)->first();

            $this->assertNull($anchor->submission_claim_token);
            $this->assertNull($anchor->submission_claimed_at);
        }

        foreach ([3, 4, 5] as $id) {
            $anchor = DB::table('blockchain_anchors')->where('id', $id)->first();

            $this->assertNotNull($anchor->submission_claim_token);
            $this->assertNotNull($anchor->submission_claimed_at);
        }

        $unclaimed = DB::table('blockchain_anchors')->where('id', 6)->first();

        $this->assertNull($unclaimed->submission_claim_token);
        $this->assertNull($unclaimed->submission_claimed_at);
    }

    public function test_stale_claim_recovery_rejects_invalid_options(): void
    {
        $this->artisan('app:release-stale-blockchain-submission-claims', [
            '--minutes' => 0,
        ])->assertExitCode(1);

        $this->artisan('app:release-stale-blockchain-submission-claims', [
            '--minutes' => 15,
            '--limit' => 0,
        ])->assertExitCode(1);
    }

    public function test_stale_claim_recovery_respects_limit(): void
    {
        \Illuminate\Support\Facades\Schema::create(
            'blockchain_anchors',
            function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->id();
                $table->string('status');
                $table->string('transaction_hash')->nullable();
                $table->uuid('submission_claim_token')->nullable();
                $table->timestamp('submission_claimed_at')->nullable();
            }
        );

        $stale = now()->subMinutes(30);

        DB::table('blockchain_anchors')->insert([
            [
                'id' => 1,
                'status' => BlockchainAnchor::STATUS_PENDING,
                'transaction_hash' => null,
                'submission_claim_token' => '11111111-1111-4111-8111-111111111111',
                'submission_claimed_at' => $stale,
            ],
            [
                'id' => 2,
                'status' => BlockchainAnchor::STATUS_FAILED,
                'transaction_hash' => null,
                'submission_claim_token' => '22222222-2222-4222-8222-222222222222',
                'submission_claimed_at' => $stale,
            ],
        ]);

        $this->artisan('app:release-stale-blockchain-submission-claims', [
            '--minutes' => 15,
            '--limit' => 1,
        ])->assertExitCode(0);

        $released = DB::table('blockchain_anchors')->where('id', 1)->first();
        $untouched = DB::table('blockchain_anchors')->where('id', 2)->first();

        $this->assertNull($released->submission_claim_token);
        $this->assertNull($released->submission_claimed_at);

        $this->assertNotNull($untouched->submission_claim_token);
        $this->assertNotNull($untouched->submission_claimed_at);
    }

}
