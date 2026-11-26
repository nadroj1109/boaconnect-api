<?php

namespace App\Jobs;

use App\Models\BlockchainAnchor;
use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SubmitBlockchainAnchor implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 15;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct(
        public int $anchorId
    ) {
    }

    public function uniqueId(): string
    {
        return 'submit-blockchain-anchor:' . $this->anchorId;
    }

    public function handle(BlockchainProofService $blockchain): void
    {
        $anchor = BlockchainAnchor::find($this->anchorId);

        if (! $anchor) {
            return;
        }

        /*
         * Fail closed: this job is exclusively for local Hardhat.
         * It must never submit to Sepolia or Ethereum Mainnet.
         */
        if (
            ! app()->environment('local')
            || config('blockchain.network') !== 'hardhat-local'
        ) {
            throw new RuntimeException(
                'Local Hardhat submission is permitted only when APP_ENV is local and the configured blockchain network is hardhat-local.'
            );
        }

        /*
         * Never submit an anchor that already has a transaction hash.
         * Reconcile a previously recorded transaction instead.
         */
        if (! empty($anchor->transaction_hash)) {
            if (
                $anchor->status === BlockchainAnchor::STATUS_SUBMITTED
            ) {
                ReconcileBlockchainAnchor::dispatch($anchor->id);
            } else {
                Log::warning(
                    'Blockchain submission skipped because the anchor already has a transaction hash.',
                    [
                        'anchor_id' => $anchor->id,
                        'status' => $anchor->status,
                        'network' => $anchor->network,
                    ]
                );
            }

            return;
        }

        if (! in_array($anchor->status, [
            BlockchainAnchor::STATUS_PENDING,
            BlockchainAnchor::STATUS_FAILED,
        ], true)) {
            // Confirmed and mismatched anchors must not be resubmitted.
            return;
        }

        if ($anchor->network !== 'hardhat-local') {
            throw new RuntimeException(
                "Anchor {$anchor->id} is not a local Hardhat anchor."
            );
        }

        $result = $blockchain->submitAnchorOnLocalHardhat($anchor);

        $state = $result['state'] ?? null;

        if ($state === 'submitted') {
            $freshAnchor = BlockchainAnchor::find($anchor->id);

            if (
                $freshAnchor
                && $freshAnchor->status === BlockchainAnchor::STATUS_SUBMITTED
                && ! empty($freshAnchor->transaction_hash)
            ) {
                ReconcileBlockchainAnchor::dispatch($freshAnchor->id);
            }

            return;
        }

        if ($state === 'already_anchored') {
            return;
        }

        if ($state === 'reconciliation_required') {
            $freshAnchor = BlockchainAnchor::find($anchor->id);

            if (
                $freshAnchor
                && $freshAnchor->status === BlockchainAnchor::STATUS_SUBMITTED
                && ! empty($freshAnchor->transaction_hash)
            ) {
                ReconcileBlockchainAnchor::dispatch($freshAnchor->id);

                return;
            }

            /*
             * No transaction hash means we cannot safely reconcile
             * transaction provenance. Do not submit again automatically.
             */
            Log::warning(
                'Blockchain anchor requires manual review; no safe automatic resubmission was attempted.',
                [
                    'anchor_id' => $anchor->id,
                    'state' => $state,
                    'transaction_hash' => $result['transaction_hash'] ?? null,
                ]
            );

            return;
        }

        if (in_array($state, [
            'submission_in_progress',
            'pending_receipt',
        ], true)) {
            throw new RuntimeException(
                "Blockchain anchor {$anchor->id} is not ready for completion; current result state is {$state}."
            );
        }

        throw new RuntimeException(
            "Unexpected submission result for blockchain anchor {$anchor->id}."
        );
    }
}