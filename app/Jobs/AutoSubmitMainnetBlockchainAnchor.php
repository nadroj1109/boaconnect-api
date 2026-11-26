<?php

namespace App\Jobs;

use App\Models\BlockchainAnchor;
use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AutoSubmitMainnetBlockchainAnchor implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(
        public int $anchorId
    ) {
    }

    public function uniqueId(): string
    {
        return 'blockchain-mainnet-auto-submit:' . $this->anchorId;
    }

    public function handle(BlockchainProofService $blockchain): void
    {
        // Fail closed: Mainnet submission is explicitly opt-in.
        if (
            ! config('blockchain.enabled', false)
            || config('blockchain.network') !== 'mainnet'
            || config('blockchain.mainnet.auto_submit') !== true
        ) {
            return;
        }

        $anchor = BlockchainAnchor::query()->find($this->anchorId);

        if (! $anchor) {
            return;
        }

        if (
            $anchor->network !== 'mainnet'
            || (int) $anchor->chain_id !== 1
        ) {
            Log::warning(
                'Automatic Mainnet submission skipped: network mismatch.',
                [
                    'anchor_id' => $anchor->id,
                    'network' => $anchor->network,
                    'chain_id' => $anchor->chain_id,
                ]
            );

            return;
        }

        // Never retry or replace a transaction that already has a hash.
        if (! empty($anchor->transaction_hash)) {
            return;
        }

        if (! in_array($anchor->status, [
            BlockchainAnchor::STATUS_PENDING,
            BlockchainAnchor::STATUS_FAILED,
        ], true)) {
            return;
        }

        $result = $blockchain->submitAnchorOnMainnetAutomatically($anchor);

        if (($result['state'] ?? null) === 'submitted') {
            ReconcileBlockchainAnchor::dispatch($anchor->id);

            return;
        }

        if (($result['state'] ?? null) === 'reconciliation_required') {
            Log::warning(
                'Automatic Mainnet submission requires reconciliation.',
                [
                    'anchor_id' => $anchor->id,
                    'transaction_hash' => $result['transaction_hash'] ?? null,
                ]
            );
        }
    }
}
