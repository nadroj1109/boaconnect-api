<?php

namespace App\Console\Commands;

use App\Models\BlockchainAnchor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseStaleBlockchainSubmissionClaims extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:release-stale-blockchain-submission-claims
                            {--minutes=15 : Minimum age of a submission claim in minutes}
                            {--limit=100 : Maximum number of stale claims to inspect}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release stale blockchain submission claims that have no transaction hash';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $minutes = filter_var(
            $this->option('minutes'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $limit = filter_var(
            $this->option('limit'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($minutes === false) {
            $this->error('--minutes must be a positive integer.');

            return self::FAILURE;
        }

        if ($limit === false) {
            $this->error('--limit must be a positive integer.');

            return self::FAILURE;
        }

        $cutoff = now()->subMinutes($minutes);

        $candidates = BlockchainAnchor::query()
            ->whereIn('status', [
                BlockchainAnchor::STATUS_PENDING,
                BlockchainAnchor::STATUS_FAILED,
            ])
            ->whereNull('transaction_hash')
            ->whereNotNull('submission_claim_token')
            ->whereNotNull('submission_claimed_at')
            ->where('submission_claimed_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit($limit)
            ->get([
                'id',
                'submission_claim_token',
                'submission_claimed_at',
            ]);

        $released = 0;

        foreach ($candidates as $candidate) {
            $updated = DB::table('blockchain_anchors')
                ->where('id', $candidate->id)
                ->whereIn('status', [
                    BlockchainAnchor::STATUS_PENDING,
                    BlockchainAnchor::STATUS_FAILED,
                ])
                ->whereNull('transaction_hash')
                ->where('submission_claim_token', $candidate->submission_claim_token)
                ->where('submission_claimed_at', $candidate->submission_claimed_at)
                ->where('submission_claimed_at', '<=', $cutoff)
                ->update([
                    'submission_claim_token' => null,
                    'submission_claimed_at' => null,
                ]);

            $released += $updated;
        }

        $this->info(
            "Released {$released} stale submission claim(s); inspected "
            . $candidates->count()
            . ' candidate(s).'
        );

        return self::SUCCESS;
    }
}
