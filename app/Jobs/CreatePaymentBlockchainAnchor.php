<?php

namespace App\Jobs;

use App\Models\BlockchainAnchor;
use App\Models\DocumentPayment;
use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use RuntimeException;

class CreatePaymentBlockchainAnchor implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 15;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct(
        public int $documentPaymentId
    ) {
    }

    public function uniqueId(): string
    {
        return 'create-payment-blockchain-anchor:' . $this->documentPaymentId;
    }

    public function handle(BlockchainProofService $blockchain): void
    {
        if (! config('blockchain.enabled', false)) {
            return;
        }

        $payment = DocumentPayment::find($this->documentPaymentId);

        if (! $payment) {
            return;
        }

        // Never anchor a payment that has not been confirmed as paid.
        if (! $payment->isPaid() || ! $payment->paid_at) {
            throw new RuntimeException(
                "Payment {$payment->id} is not confirmed as paid."
            );
        }

        $network = config('blockchain.network');

        if ($network === 'sepolia') {
            $anchor = $blockchain->createSepoliaPaymentAnchor($payment);
        } elseif ($network === 'hardhat-local') {
            $anchor = $blockchain->createPaymentAnchor($payment);
        } elseif ($network === 'mainnet') {
            $anchor = $blockchain->createMainnetPaymentAnchor($payment);
        } else {
            throw new RuntimeException(
                'Unsupported blockchain network for payment anchoring.'
            );
        }

        if (! in_array($anchor->status, [
            BlockchainAnchor::STATUS_PENDING,
            BlockchainAnchor::STATUS_SUBMITTED,
            BlockchainAnchor::STATUS_CONFIRMED,
        ], true)) {
            throw new RuntimeException(
                "Payment anchor {$anchor->id} is in state {$anchor->status}."
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
