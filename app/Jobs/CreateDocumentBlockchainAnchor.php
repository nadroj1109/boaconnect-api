<?php

namespace App\Jobs;

use App\Models\BlockchainAnchor;
use App\Models\DocumentFile;
use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use RuntimeException;

class CreateDocumentBlockchainAnchor implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 15;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct(
        public int $documentFileId
    ) {
    }

    public function uniqueId(): string
    {
        return 'create-document-blockchain-anchor:' . $this->documentFileId;
    }

    public function handle(BlockchainProofService $blockchain): void
    {
        if (! config('blockchain.enabled', false)) {
            return;
        }

        $file = DocumentFile::find($this->documentFileId);

        if (! $file) {
            return;
        }

        $network = config('blockchain.network');

        if ($network === 'sepolia') {
            $anchor = $blockchain->createSepoliaDocumentAnchor($file);
        } elseif ($network === 'hardhat-local') {
            $anchor = $blockchain->createDocumentAnchor($file);
        } elseif ($network === 'mainnet') {
            $anchor = $blockchain->createMainnetDocumentAnchor($file);
        } else {
            throw new RuntimeException(
                'Unsupported blockchain network for document anchoring.'
            );
        }

        if (! in_array($anchor->status, [
            BlockchainAnchor::STATUS_PENDING,
            BlockchainAnchor::STATUS_SUBMITTED,
            BlockchainAnchor::STATUS_CONFIRMED,
        ], true)) {
            throw new RuntimeException(
                "Document anchor {$anchor->id} is in state {$anchor->status}."
            );
        }

        // Only eligible pending anchors without a transaction hash
        // can be queued for automatic submission.
        if (
            $anchor->status !== BlockchainAnchor::STATUS_PENDING
            || ! empty($anchor->transaction_hash)
        ) {
            return;
        }

        if (
            $network === 'sepolia'
            && config('blockchain.sepolia.auto_submit') === true
        ) {
            Bus::dispatch(new AutoSubmitSepoliaBlockchainAnchor($anchor->id));

            return;
        }

        if (
            $network === 'mainnet'
            && config('blockchain.mainnet.auto_submit') === true
        ) {
            Bus::dispatch(new AutoSubmitMainnetBlockchainAnchor($anchor->id));
        }
    }
}
