<?php

namespace App\Console\Commands;

use App\Models\BlockchainAnchor;
use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Console\Command;
use RuntimeException;

class SubmitSepoliaBlockchainAnchor extends Command
{
    protected $signature = 'blockchain:submit-sepolia {anchorId}';

    protected $description = 'Explicitly authorize submission of one pending Sepolia blockchain anchor';

    public function handle(BlockchainProofService $blockchain): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('This command requires an interactive terminal.');

            return self::FAILURE;
        }

        if (! config('blockchain.enabled', false)) {
            $this->error('Blockchain integration is disabled.');

            return self::FAILURE;
        }

        if (config('blockchain.network') !== 'sepolia') {
            $this->error('The configured blockchain network must be sepolia.');

            return self::FAILURE;
        }

        if ((int) config('blockchain.sepolia.chain_id') !== 11155111) {
            $this->error('The configured Sepolia chain ID must be 11155111.');

            return self::FAILURE;
        }

        $anchor = BlockchainAnchor::query()->find($this->argument('anchorId'));

        if (! $anchor) {
            $this->error('Blockchain anchor not found.');

            return self::FAILURE;
        }

        if ($anchor->network !== 'sepolia') {
            $this->error('The selected anchor does not belong to Sepolia.');

            return self::FAILURE;
        }

        if (! in_array($anchor->status, [
            BlockchainAnchor::STATUS_PENDING,
            BlockchainAnchor::STATUS_FAILED,
        ], true) || ! empty($anchor->transaction_hash)) {
            $this->error(
                'Only pending or failed anchors without a transaction hash can be submitted. Reconcile existing transactions separately.'
            );

            return self::FAILURE;
        }

        $this->warn('LIVE SEPOLIA TRANSACTION');
        $this->line('Anchor ID: ' . $anchor->getKey());
        $this->line('Network: Sepolia (chain ID 11155111)');
        $this->line('Anchor UUID: ' . $anchor->uuid);
        $this->line('Proof hash: ' . $anchor->proof_hash);
        $this->line('Contract: ' . $anchor->contract_address);
        $this->line('Status: ' . $anchor->status);
        $this->newLine();
        $this->warn('This action can broadcast a real transaction and spend testnet ETH.');

        $expectedConfirmation = 'SUBMIT-SEPOLIA-ANCHOR:' . $anchor->getKey();
        $enteredConfirmation = $this->ask(
            'To authorize this exact anchor, type: ' . $expectedConfirmation
        );

        if (! is_string($enteredConfirmation)
            || ! hash_equals($expectedConfirmation, trim($enteredConfirmation))) {
            $this->error('Confirmation did not match. Nothing was submitted.');

            return self::FAILURE;
        }

        try {
            $result = $blockchain->submitAnchorOnSepolia(
                $anchor,
                $enteredConfirmation
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Submission result: ' . ($result['state'] ?? 'unknown'));

        if (! empty($result['transaction_hash'])) {
            $this->line('Transaction hash: ' . $result['transaction_hash']);
        }

        if (! empty($result['message'])) {
            $this->line($result['message']);
        }

        return self::SUCCESS;
    }
}