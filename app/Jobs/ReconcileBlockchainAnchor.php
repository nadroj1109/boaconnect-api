<?php

namespace App\Jobs;

use App\Models\BlockchainAnchor;
use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class ReconcileBlockchainAnchor implements ShouldQueue, ShouldBeUnique
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

    protected function findAnchor(): ?BlockchainAnchor
    {
        return BlockchainAnchor::find($this->anchorId);
    }

    public function uniqueId(): string
    {
        return 'blockchain-anchor-reconciliation:' . $this->anchorId;
    }

    public function handle(BlockchainProofService $blockchain): void
    {
        $anchor = $this->findAnchor();

        if (! $anchor) {
            return;
        }

        // Only reconcile transactions already submitted to the chain.
        if ($anchor->status !== BlockchainAnchor::STATUS_SUBMITTED) {
            return;
        }

        $result = $blockchain->confirmAnchor($anchor);

        // A missing receipt is temporary: let Laravel retry the job.
        if (($result['state'] ?? null) === 'pending_receipt') {
            throw new RuntimeException(
                "Blockchain transaction receipt is not available yet for anchor {$anchor->id}."
            );
        }
    }
}
