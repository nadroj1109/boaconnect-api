<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileBlockchainAnchor;
use App\Models\BlockchainAnchor;
use Illuminate\Console\Command;

class ReconcileBlockchainAnchors extends Command
{
    protected $signature = 'app:reconcile-blockchain-anchors
                            {--limit=100 : Maximum number of anchors to dispatch}';

    protected $description =
        'Dispatch reconciliation jobs for submitted blockchain anchors';

    public function handle(): int
    {
        $limit = filter_var(
            $this->option('limit'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($limit === false) {
            $this->error('The --limit option must be a positive integer.');

            return self::FAILURE;
        }

        $dispatched = 0;

        BlockchainAnchor::query()
            ->where('status', BlockchainAnchor::STATUS_SUBMITTED)
            ->whereNotNull('transaction_hash')
            ->where('transaction_hash', '!=', '')
            ->orderBy('id')
            ->limit($limit)
            ->eachById(function (BlockchainAnchor $anchor) use (&$dispatched): void {
                ReconcileBlockchainAnchor::dispatch($anchor->id);

                $dispatched++;
            });

        $this->info("Processed {$dispatched} eligible anchor(s) for reconciliation.");

        return self::SUCCESS;
    }
}
