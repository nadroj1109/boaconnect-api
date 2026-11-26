<?php

namespace App\Jobs;

use App\Models\BlockchainAnchor;
use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AutoSubmitSepoliaBlockchainAnchor implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(
        public int $anchorId
    ) {
    }

    public function uniqueId(): string
    {
        return 'blockchain-sepolia-auto-submit:' . $this->anchorId;
    }

    public function handle(BlockchainProofService $blockchain): void
    {
        // Fail closed: automatic public-chain submission is opt-in.
        if (
            ! config('blockchain.enabled', false)
            || config('blockchain.network') !== 'sepolia'
            || config('blockchain.sepolia.auto_submit') !== true
        ) {
            return;
        }

        $anchor = BlockchainAnchor::query()->find($this->anchorId);

        if (! $anchor) {
            return;
        }

        if (
            $anchor->network !== 'sepolia'
            || (int) $anchor->chain_id !== 11155111
        ) {
            Log::warning('Automatic Sepolia submission skipped: network mismatch.', [
                'anchor_id' => $anchor->id,
                'network' => $anchor->network,
                'chain_id' => $anchor->chain_id,
            ]);

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

        $result = $blockchain->submitAnchorOnSepoliaAutomatically($anchor);

        if (($result['state'] ?? null) === 'submitted') {
            ReconcileBlockchainAnchor::dispatch($anchor->id);
            return;
        }

        if (($result['state'] ?? null) === 'reconciliation_required') {
            $transactionHash = $result['transaction_hash'] ?? null;

            Log::warning('Automatic Sepolia submission requires reconciliation.', [
                'anchor_id' => $anchor->id,
                'transaction_hash' => $transactionHash,
            ]);

            if (! empty($transactionHash)) {
                ReconcileBlockchainAnchor::dispatch($anchor->id);
            }
        }
    }
}
