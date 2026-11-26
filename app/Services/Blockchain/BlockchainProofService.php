<?php

namespace App\Services\Blockchain;

use App\Models\BlockchainAnchor;
use App\Models\DocumentFile;
use App\Models\DocumentPayment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Web3p\EthereumTx\EIP1559Transaction;
use Web3p\EthereumTx\Transaction;
use Web3p\EthereumUtil\Util;

class BlockchainProofService
{
    /**
     * Send a JSON-RPC request to the configured blockchain node.
     */
    private function rpc(string $method, array $params = []): mixed
    {
        if (! config('blockchain.enabled')) {
            throw new RuntimeException(
                'Blockchain integration is disabled.'
            );
        }

        $response = Http::timeout(10)
            ->post(config('blockchain.rpc_url'), [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 1,
            ])
            ->throw();

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException(
                'Invalid JSON-RPC response.'
            );
        }

        if (isset($payload['error'])) {
            throw new RuntimeException(
                'Blockchain RPC error: ' .
                ($payload['error']['message'] ?? 'Unknown error.')
            );
        }

        if (! array_key_exists('result', $payload)) {
            throw new RuntimeException(
                'The blockchain RPC response has no result.'
            );
        }

        return $payload['result'];
    }

    /**
     * Resolve RPC settings for a supported blockchain network.
     */
    private function networkSettings(string $network): array
    {
        if (! config('blockchain.enabled')) {
            throw new RuntimeException(
                'Blockchain integration is disabled.'
            );
        }

        $settings = match ($network) {
            'hardhat-local' => [
                'network' => 'hardhat-local',
                'rpc_url' => config('blockchain.hardhat_local.rpc_url'),
                'chain_id' => (int) config('blockchain.hardhat_local.chain_id'),
                'contract_address' => config(
                    'blockchain.hardhat_local.contract_address'
                ),
            ],
            'sepolia' => [
                'network' => 'sepolia',
                'rpc_url' => config('blockchain.sepolia.rpc_url'),
                'chain_id' => (int) config('blockchain.sepolia.chain_id'),
                'contract_address' => config(
                    'blockchain.sepolia.contract_address'
                ),
            ],
            'mainnet' => [
                'network' => 'mainnet',
                'rpc_url' => config('blockchain.mainnet.rpc_url'),
                'chain_id' => (int) config('blockchain.mainnet.chain_id'),
                'contract_address' => config(
                    'blockchain.mainnet.contract_address'
                ),
            ],
            'polygon' => [
                'network' => 'polygon',
                'rpc_url' => config('blockchain.polygon.rpc_url'),
                'chain_id' => (int) config('blockchain.polygon.chain_id'),
                'contract_address' => config(
                    'blockchain.polygon.contract_address'
                ),
            ],
            default => throw new RuntimeException(
                'The requested blockchain network is not supported.'
            ),
        };

        if (
            ! is_string($settings['rpc_url'])
            || ! filter_var($settings['rpc_url'], FILTER_VALIDATE_URL)
        ) {
            throw new RuntimeException(
                'The configured blockchain RPC URL is invalid.'
            );
        }

        if (
            ! is_string($settings['contract_address'])
            || ! preg_match(
                '/^0x[a-fA-F0-9]{40}$/',
                $settings['contract_address']
            )
        ) {
            throw new RuntimeException(
                'The configured blockchain contract address is invalid.'
            );
        }

        $expectedChainIds = [
            'hardhat-local' => 31337,
            'sepolia' => 11155111,
            'mainnet' => 1,
            'polygon' => 137,
        ];

        if ($settings['chain_id'] !== $expectedChainIds[$network]) {
            throw new RuntimeException(
                "The configured {$network} chain ID is invalid."
            );
        }

        return $settings;
    }

    /**
     * Send a JSON-RPC request to a specifically selected network.
     */
    private function rpcForNetwork(
        string $network,
        string $method,
        array $params = []
    ): mixed {
        $settings = $this->networkSettings($network);

        $response = Http::timeout(10)
            ->post($settings['rpc_url'], [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 1,
            ])
            ->throw();

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException(
                'Invalid JSON-RPC response.'
            );
        }

        if (isset($payload['error'])) {
            throw new RuntimeException(
                'Blockchain RPC error: ' .
                ($payload['error']['message'] ?? 'Unknown error.')
            );
        }

        if (! array_key_exists('result', $payload)) {
            throw new RuntimeException(
                'The blockchain RPC response has no result.'
            );
        }

        return $payload['result'];
    }

    /**
     * Parse a JSON-RPC hexadecimal quantity as a safe PHP integer.
     */
    private function parseRpcQuantity(
        mixed $value,
        string $field
    ): int {
        if (
            ! is_string($value)
            || ! preg_match('/^0x(?:0|[1-9a-fA-F][0-9a-fA-F]*)$/', $value)
        ) {
            throw new RuntimeException(
                "The blockchain RPC returned an invalid {$field} quantity."
            );
        }

        $hex = substr($value, 2);

        // Keep the conversion within the signed PHP integer range.
        $maxHex = strtolower(dechex(PHP_INT_MAX));

        if (
            strlen($hex) > strlen($maxHex)
            || (
                strlen($hex) === strlen($maxHex)
                && strcmp(strtolower($hex), $maxHex) > 0
            )
        ) {
            throw new RuntimeException(
                "The blockchain RPC {$field} quantity exceeds the supported integer range."
            );
        }

        return (int) hexdec($hex);
    }

    /**
     * Convert a non-negative integer into a JSON-RPC hexadecimal quantity.
     */
    private function rpcQuantity(int $value): string
    {
        if ($value < 0) {
            throw new RuntimeException(
                'A blockchain transaction quantity cannot be negative.'
            );
        }

        return '0x' . dechex($value);
    }

    /**
     * Parse a JSON-RPC hexadecimal quantity as a safe PHP integer.
     */
    private function parseSepoliaRpcQuantity(
        mixed $value,
        string $field
    ): int {
        if (
            ! is_string($value)
            || ! preg_match('/^0x(?:0|[1-9a-fA-F][0-9a-fA-F]*)$/', $value)
        ) {
            throw new RuntimeException(
                "The Sepolia RPC returned an invalid {$field} quantity."
            );
        }

        $hex = substr($value, 2);

        // Keep the conversion within the signed PHP integer range.
        $maxHex = strtolower(dechex(PHP_INT_MAX));

        if (
            strlen($hex) > strlen($maxHex)
            || (
                strlen($hex) === strlen($maxHex)
                && strcmp(strtolower($hex), $maxHex) > 0
            )
        ) {
            throw new RuntimeException(
                "The Sepolia RPC {$field} quantity exceeds the supported integer range."
            );
        }

        return (int) hexdec($hex);
    }

    /**
     * Convert a non-negative integer into a JSON-RPC hexadecimal quantity.
     */
    private function sepoliaRpcQuantity(int $value): string
    {
        if ($value < 0) {
            throw new RuntimeException(
                'A Sepolia transaction quantity cannot be negative.'
            );
        }

        return '0x' . dechex($value);
    }

    protected function findDocumentFile(int $id): DocumentFile
    {
        return DocumentFile::findOrFail($id);
    }
         
    /**
     * Retrieve the connected node's chain ID.
     */
    public function getChainId(string $network = 'hardhat-local'): int
    {
        $result = $this->rpcForNetwork(
            $network,
            'eth_chainId'
        );

        if (! is_string($result)) {
            throw new RuntimeException(
                'Invalid blockchain chain ID response.'
            );
        }

        return $this->parseRpcQuantity($result, 'chain ID');
    }

    /**
     * Retrieve a transaction receipt without modifying local records.
     */
    public function getTransactionReceipt(
        string $transactionHash,
        string $network = 'hardhat-local'
    ): ?array {
        if (! preg_match('/^0x[a-fA-F0-9]{64}$/', $transactionHash)) {
            throw new RuntimeException('Invalid blockchain transaction hash.');
        }

        $receipt = $this->rpcForNetwork(
            $network,
            'eth_getTransactionReceipt',
            [$transactionHash]
        );

        return is_array($receipt) ? $receipt : null;
    }

    /**
     * Validate the expected ProofAnchored event in a transaction receipt.
     *
     * This method is read-only and does not modify the anchor.
     */
    public function validateProofAnchoredEvent(
        array $receipt,
        BlockchainAnchor $anchor
    ): bool {
        if (! $anchor->exists || empty($anchor->uuid)) {
            throw new RuntimeException(
                'A persisted blockchain anchor with a UUID is required.'
            );
        }

        if (! is_array($receipt['logs'] ?? null)) {
            throw new RuntimeException(
                'The transaction receipt does not contain a valid logs array.'
            );
        }

        if (! preg_match('/^[a-fA-F0-9]{64}$/', (string) $anchor->proof_hash)) {
            throw new RuntimeException('The stored proof hash is invalid.');
        }

        $contractAddress = strtolower(
            (string) $anchor->contract_address
        );

        if (! preg_match('/^0x[a-f0-9]{40}$/', $contractAddress)) {
            throw new RuntimeException(
                'The stored blockchain contract address is invalid.'
            );
        }

        $expectedProofId = strtolower(
            $this->proofIdForAnchor($anchor)
        );

        $expectedProofHash = strtolower(
            '0x' . $anchor->proof_hash
        );

        $expectedType = match ($anchor->proof_type) {
            BlockchainAnchor::TYPE_DOCUMENT => 1,
            BlockchainAnchor::TYPE_PAYMENT => 2,
            default => throw new RuntimeException(
                'The blockchain anchor has an invalid proof type.'
            ),
        };

        $expectedTopic0 =
            '0x1b4cfbc64ccb68d5eac80a15ad8f8b531baffdf2109fe1c5302e8fc9b9c99f84';

        foreach ($receipt['logs'] as $log) {
            if (! is_array($log)) {
                continue;
            }

            if (
                strtolower((string) ($log['address'] ?? ''))
                !== $contractAddress
            ) {
                continue;
            }

            $topics = $log['topics'] ?? [];

            if (
                ! is_array($topics)
                || count($topics) !== 4
                || ! is_string($topics[0] ?? null)
                || ! is_string($topics[1] ?? null)
                || ! is_string($topics[2] ?? null)
                || ! is_string($topics[3] ?? null)
            ) {
                continue;
            }

            if (
                strtolower($topics[0]) !== $expectedTopic0
                || strtolower($topics[1]) !== $expectedProofId
                || strtolower($topics[2]) !== $expectedProofHash
            ) {
                continue;
            }

            $anchoredByTopic = strtolower($topics[3]);

            if (! preg_match('/^0x[a-f0-9]{64}$/', $anchoredByTopic)) {
                continue;
            }

            // The indexed address occupies the last 20 bytes of its topic.
            if (substr($anchoredByTopic, 2, 24) !== str_repeat('0', 24)) {
                continue;
            }

            $data = $log['data'] ?? null;

            // Two ABI words: uint8 proofType and uint64 anchoredAt.
            if (
                ! is_string($data)
                || ! preg_match('/^0x[a-fA-F0-9]{128}$/', $data)
            ) {
                continue;
            }

            $encoded = substr($data, 2);
            $proofTypeHex = substr($encoded, 0, 64);
            $anchoredAtHex = substr($encoded, 64, 64);

            // Reject malformed ABI words instead of accepting truncated values.
            if (
                hexdec(substr($proofTypeHex, 0, 62)) !== 0
                || hexdec(substr($anchoredAtHex, 0, 48)) !== 0
            ) {
                continue;
            }

            $proofType = (int) hexdec(substr($proofTypeHex, 62, 2));

            if ($proofType !== $expectedType) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Confirm a submitted anchor after validating its receipt and on-chain proof.
     */
    public function confirmAnchor(BlockchainAnchor $anchor): array
    {
        if (! $anchor->exists || empty($anchor->transaction_hash)) {
            throw new RuntimeException(
                'A persisted anchor with a transaction hash is required.'
            );
        }

        $network = (string) $anchor->network;
        $settings = $this->networkSettings($network);

        $this->assertReady($network);

        if ((int) $anchor->chain_id !== $this->getChainId($network)
            || (int) $anchor->chain_id !== (int) $settings['chain_id']) {
            throw new RuntimeException(
                'The anchor chain ID does not match its configured blockchain network.'
            );
        }

        $configuredAddress = $settings['contract_address'] ?? null;

        if (
            ! is_string($configuredAddress) ||
            ! preg_match('/^0x[a-fA-F0-9]{40}$/', $configuredAddress)
        ) {
            throw new RuntimeException(
                'The configured blockchain contract address is invalid.'
            );
        }

        if (
            ! is_string($anchor->contract_address) ||
            ! hash_equals(
                strtolower($configuredAddress),
                strtolower($anchor->contract_address)
            )
        ) {
            throw new RuntimeException(
                'The anchor contract address does not match its configured blockchain network.'
            );
        }

        if (! in_array($anchor->status, [
            BlockchainAnchor::STATUS_SUBMITTED,
            BlockchainAnchor::STATUS_CONFIRMED,
        ], true)) {
            throw new RuntimeException(
                'Only submitted or already-confirmed anchors can be confirmed.'
            );
        }

        $receipt = $this->getTransactionReceipt(
            $anchor->transaction_hash,
            $network
        );

        if ($receipt === null) {
            return [
                'anchor_id' => $anchor->id,
                'state' => 'pending_receipt',
            ];
        }

        if (
            strtolower((string) ($receipt['transactionHash'] ?? ''))
                !== strtolower((string) $anchor->transaction_hash)
            || strtolower((string) ($receipt['to'] ?? ''))
                !== strtolower((string) $anchor->contract_address)
        ) {
            throw new RuntimeException(
                'The transaction receipt hash or destination does not match the anchor.'
            );
        }

        if (($receipt['status'] ?? null) !== '0x1') {
            $anchor->forceFill([
                'status' => BlockchainAnchor::STATUS_FAILED,
                'last_error' => 'The blockchain transaction receipt reports failure.',
            ])->save();

            return [
                'anchor_id' => $anchor->id,
                'state' => 'transaction_failed',
            ];
        }

        $blockHex = $receipt['blockNumber'] ?? null;

        if (
            ! is_string($blockHex) ||
            ! preg_match('/^0x[0-9a-fA-F]+$/', $blockHex)
        ) {
            throw new RuntimeException(
                'The transaction receipt has an invalid block number.'
            );
        }

        if (! $this->validateProofAnchoredEvent($receipt, $anchor)) {
            $anchor->forceFill([
                'status' => BlockchainAnchor::STATUS_MISMATCH,
                'last_error' => 'The transaction receipt does not contain the expected ProofAnchored event.',
            ])->save();

            return [
                'anchor_id' => $anchor->id,
                'state' => 'event_mismatch',
            ];
        }

        $inspection = $this->inspectAnchorOnChain($anchor);

        if ($inspection['state'] !== 'matching') {
            $anchor->forceFill([
                'status' => BlockchainAnchor::STATUS_MISMATCH,
                'last_error' => 'The confirmed transaction does not have a matching on-chain proof.',
            ])->save();

            return [
                'anchor_id' => $anchor->id,
                'state' => 'proof_mismatch',
                'inspection' => $inspection,
            ];
        }

        $blockNumber = (int) hexdec($blockHex);

        $anchor->forceFill([
            'status' => BlockchainAnchor::STATUS_CONFIRMED,
            'block_number' => $blockNumber,
            'confirmed_at' => $anchor->confirmed_at ?? now(),
            'last_error' => null,
        ])->save();

        return [
            'anchor_id' => $anchor->id,
            'state' => 'confirmed',
            'transaction_hash' => $anchor->transaction_hash,
            'block_number' => $blockNumber,
            'proof_id' => $inspection['proof_id'],
        ];
    }

    /**
     * Ensure the connected node matches the configured chain ID.
     */
    protected function assertExpectedChain(
        string $network = 'hardhat-local'
    ): void {
        $settings = $this->networkSettings($network);
        $expectedChainId = (int) $settings['chain_id'];
        $actualChainId = $this->getChainId($network);

        if ($actualChainId !== $expectedChainId) {
            throw new RuntimeException(
                "Wrong blockchain network. Expected chain ID {$expectedChainId}, got {$actualChainId}."
            );
        }
    }
    
    /**
     * Confirm the configured contract has deployed bytecode.
     */
    public function isContractDeployed(
        string $network = 'hardhat-local'
    ): bool {
        $settings = $this->networkSettings($network);
        $address = $settings['contract_address'];

        if (
            ! is_string($address) ||
            ! preg_match('/^0x[a-fA-F0-9]{40}$/', $address)
        ) {
            throw new RuntimeException(
                'The blockchain contract address is invalid.'
            );
        }

        $code = $this->rpcForNetwork(
            $network,
            'eth_getCode',
            [$address, 'latest']
        );

        if (
            ! is_string($code) ||
            ! preg_match('/^0x(?:[a-fA-F0-9]{2})*$/', $code)
        ) {
            throw new RuntimeException(
                'The blockchain RPC returned an invalid contract bytecode response.'
            );
        }

        return strtolower($code) !== '0x'
            && strtolower($code) !== '0x0';
    }

    private function assertBlockchainAnchorCreationEnabled(): void
    {
        if (! config('blockchain.enabled', false)) {
            throw new \RuntimeException(
                'Blockchain integration is disabled. Anchor creation is not allowed.'
            );
        }
    }

    /**
     * Verify the expected chain and deployed registry contract.
     */
    protected function assertReady(
        string $network = 'hardhat-local'
    ): void {
        $settings = $this->networkSettings($network);

        $this->assertExpectedChain($network);

        if (! $this->isContractDeployed($network)) {
            throw new RuntimeException(
                'The blockchain proof registry contract is not deployed on the selected network.'
            );
        }

        if (
            ! is_string($settings['contract_address'] ?? null) ||
            ! preg_match(
                '/^0x[a-fA-F0-9]{40}$/',
                $settings['contract_address']
            )
        ) {
            throw new RuntimeException(
                'The blockchain contract address is invalid.'
            );
        }
    }

    /**
     * Check whether an address is authorized to write proofs
     * to the selected registry.
     */
    public function isWriter(
        string $signer,
        string $network = 'hardhat-local'
    ): bool {
        if (! preg_match('/^0x[a-fA-F0-9]{40}$/', $signer)) {
            throw new RuntimeException(
                'The blockchain signer address is invalid.'
            );
        }

        $settings = $this->networkSettings($network);

        // writers(address): 0x8e7e80a2
        // ABI-encode the address as a left-padded 32-byte word.
        $addressWord = str_pad(
            strtolower(substr($signer, 2)),
            64,
            '0',
            STR_PAD_LEFT
        );

        $data = '0x8e7e80a2' . $addressWord;

        $result = $this->rpcForNetwork($network, 'eth_call', [
            [
                'to' => $settings['contract_address'],
                'data' => $data,
            ],
            'latest',
        ]);

        if (
            ! is_string($result) ||
            ! preg_match('/^0x[a-fA-F0-9]{64}$/', $result)
        ) {
            throw new RuntimeException(
                'The blockchain returned an invalid writer-permission response.'
            );
        }

        $word = strtolower(substr($result, 2));

        if ($word === str_repeat('0', 64)) {
            return false;
        }

        if ($word === str_repeat('0', 63) . '1') {
            return true;
        }

        throw new RuntimeException(
            'The blockchain returned a non-canonical writer-permission value.'
        );
    }

    /**
     * Read a proof from the deployed registry.
     *
     * The returned structure is ABI-decoded locally.
     */
    public function getProof(
        string $proofId,
        string $network = 'hardhat-local'
    ): array {
        if (! preg_match('/^0x[a-fA-F0-9]{64}$/', $proofId)) {
            throw new RuntimeException(
                'The proof ID must be a 32-byte hexadecimal value.'
            );
        }

        $settings = $this->networkSettings($network);
        $data = '0x1b80bb3a' . substr($proofId, 2);

        $result = $this->rpcForNetwork($network, 'eth_call', [
            [
                'to' => $settings['contract_address'],
                'data' => $data,
            ],
            'latest',
        ]);

        if (
            ! is_string($result) ||
            ! preg_match('/^0x[a-fA-F0-9]{320}$/', $result)
        ) {
            throw new RuntimeException(
                'The blockchain returned an invalid proof response.'
            );
        }

        $encoded = substr($result, 2);
        $words = str_split($encoded, 64);

        $proofHash = '0x' . $words[0];
        $anchoredAt = (int) hexdec($words[1]);
        $proofType = (int) hexdec($words[2]);
        $anchoredBy = '0x' . substr($words[3], 24);
        $exists = hexdec($words[4]) === 1;

        return [
            'proof_id' => strtolower($proofId),
            'proof_hash' => strtolower($proofHash),
            'anchored_at' => $anchoredAt,
            'proof_type' => $proofType,
            'anchored_by' => strtolower($anchoredBy),
            'exists' => $exists,
        ];
    }

    /**
     * Verify a proof hash and type against the registry.
     */
    public function verifyProof(
        string $proofId,
        string $expectedHash,
        int $expectedType,
        string $network = 'hardhat-local'
    ): bool {
        if (! preg_match('/^0x[a-fA-F0-9]{64}$/', $expectedHash)) {
            throw new RuntimeException(
                'The expected proof hash must be a 32-byte hexadecimal value.'
            );
        }

        if (! in_array($expectedType, [1, 2], true)) {
            throw new RuntimeException(
                'The expected proof type must be 1 (document) or 2 (payment).'
            );
        }

        $proof = $this->getProof($proofId, $network);

        return $proof['exists']
            && hash_equals(
                strtolower($expectedHash),
                $proof['proof_hash']
            )
            && $proof['proof_type'] === $expectedType;
    }

    /**
     * Prepare a document proof from an existing finalized PDF.
     *
     * This method does not create an anchor or submit a transaction.
     */
    public function prepareDocumentProof(DocumentFile $file): array
    {
        if (empty($file->disk) || empty($file->path)) {
            throw new RuntimeException(
                'The document storage disk or path is missing.'
            );
        }

        if (empty($file->checksum)) {
            throw new RuntimeException(
                'The document checksum is missing.'
            );
        }

        if (
            ! preg_match('/^[a-fA-F0-9]{64}$/', $file->checksum)
        ) {
            throw new RuntimeException(
                'The stored document checksum is invalid.'
            );
        }

        if ($file->mime_type !== 'application/pdf') {
            throw new RuntimeException(
                'Only finalized PDF documents can be prepared for anchoring.'
            );
        }

        $disk = Storage::disk($file->disk);

        if (! $disk->exists($file->path)) {
            throw new RuntimeException(
                'The finalized document file could not be found.'
            );
        }

        $contents = $disk->get($file->path);
        $actualHash = hash('sha256', $contents);

        if (! hash_equals(
            strtolower($file->checksum),
            $actualHash
        )) {
            throw new RuntimeException(
                'The stored PDF checksum does not match the database checksum.'
            );
        }

        return [
            'proof_type' => 'document',
            'proof_version' => 1,
            'document_file_id' => (int) $file->id,
            'document_uuid' => $file->uuid,
            'proof_hash' => $actualHash,
        ];
    }

    /**
     * Prepare a canonical payment proof.
     *
     * This method does not create an anchor or submit a transaction.
     */
    public function preparePaymentProof(DocumentPayment $payment): array
    {
        if (! $payment->exists || ! $payment->isPaid()) {
            throw new RuntimeException(
                'Only persisted, paid payments can be prepared for anchoring.'
            );
        }

        if ($payment->paid_at === null) {
            throw new RuntimeException(
                'The paid timestamp is missing.'
            );
        }

        // Parse the decimal string without floating-point arithmetic.
        $amount = (string) $payment->getRawOriginal('amount');

        if (! preg_match('/^\d+\.\d{2}$/', $amount)) {
            throw new RuntimeException(
                'The payment amount must have exactly two decimal places.'
            );
        }

        [$whole, $fraction] = explode('.', $amount, 2);

        $amountMinor = ((int) $whole * 100) + (int) $fraction;

        if ($amountMinor <= 0) {
            throw new RuntimeException(
                'The payment amount must be greater than zero.'
            );
        }

        $provider = $payment->provider?->value;
        $paymentMethod = $payment->payment_method?->value;

        if (! is_string($provider) || ! is_string($paymentMethod)) {
            throw new RuntimeException(
                'The payment provider or method is invalid.'
            );
        }

        $canonical = [
            'proof_type' => 'payment',
            'proof_version' => 1,
            'payment_id' => (int) $payment->id,
            'amount_minor' => $amountMinor,
            'currency' => 'PHP',
            'provider' => $provider,
            'payment_method' => $paymentMethod,
            'status' => 'paid',
            'paid_at' => $payment->paid_at
                ->copy()
                ->utc()
                ->format('Y-m-d\TH:i:s\Z'),
        ];

        $canonicalJson = json_encode(
            $canonical,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        return [
            ...$canonical,
            'proof_hash' => hash('sha256', $canonicalJson),
        ];
    }

    /**
     * Derive a stable bytes32 proof ID from an anchor UUID.
     *
     * The same anchor always produces the same proof ID.
     * This uses SHA-256, not Ethereum Keccak-256.
     */
    public function proofIdForAnchor(BlockchainAnchor $anchor): string
    {
        if (! $anchor->exists || empty($anchor->uuid)) {
            throw new RuntimeException(
                'A persisted blockchain anchor with a UUID is required.'
            );
        }

        return '0x' . hash(
            'sha256',
            'BOAConnect:anchor:v1:' . $anchor->uuid
        );
    }

    /**
     * Inspect an anchor against its recorded blockchain network.
     *
     * This method is read-only and does not change the database.
     */
    public function inspectAnchorOnChain(BlockchainAnchor $anchor): array
    {
        if (! $anchor->exists) {
            throw new RuntimeException(
                'A persisted blockchain anchor is required.'
            );
        }

        $network = (string) $anchor->network;
        $settings = $this->networkSettings($network);

        $this->assertReady($network);

        if (! in_array($anchor->proof_type, [
            BlockchainAnchor::TYPE_DOCUMENT,
            BlockchainAnchor::TYPE_PAYMENT,
        ], true)) {
            throw new RuntimeException(
                'The blockchain anchor has an invalid proof type.'
            );
        }

        if (! preg_match('/^[a-fA-F0-9]{64}$/', $anchor->proof_hash)) {
            throw new RuntimeException(
                'The stored anchor proof hash is invalid.'
            );
        }

        if ((int) $anchor->chain_id !== (int) $settings['chain_id']) {
            throw new RuntimeException(
                'The anchor chain ID does not match its configured blockchain network.'
            );
        }

        $configuredAddress = $settings['contract_address'] ?? null;

        if (
            ! is_string($configuredAddress) ||
            ! preg_match('/^0x[a-fA-F0-9]{40}$/', $configuredAddress)
        ) {
            throw new RuntimeException(
                'The configured blockchain contract address is invalid.'
            );
        }

        if (
            ! is_string($anchor->contract_address) ||
            ! hash_equals(
                strtolower($configuredAddress),
                strtolower($anchor->contract_address)
            )
        ) {
            throw new RuntimeException(
                'The anchor contract address does not match its configured blockchain network.'
            );
        }

        $proofId = $this->proofIdForAnchor($anchor);
        $proof = $this->getProof($proofId, $network);

        if (! $proof['exists']) {
            $state = 'missing';
        } else {
            $expectedType = $anchor->proof_type === BlockchainAnchor::TYPE_DOCUMENT
                ? 1
                : 2;

            $expectedHash = '0x' . strtolower($anchor->proof_hash);

            $state = hash_equals($expectedHash, $proof['proof_hash'])
                && $proof['proof_type'] === $expectedType
                    ? 'matching'
                    : 'mismatch';
        }

        return [
            'anchor_id' => $anchor->id,
            'proof_id' => $proofId,
            'state' => $state,
            'on_chain_proof' => $proof,
        ];
    }

    /**
     * Submit a proof using the unlocked signer on a local Hardhat node only.
     *
     * Never use this method for a public or production blockchain.
     */
    public function submitAnchorOnLocalHardhat(BlockchainAnchor $anchor): array
    {
        if (! $anchor->exists || ! $anchor->getKey()) {
            throw new RuntimeException(
                'A persisted blockchain anchor is required.'
            );
        }

        $anchorId = $anchor->getKey();

        $lock = Cache::store()->getStore()->lock(
            'blockchain:hardhat-local:anchor:' . $anchorId,
            600
        );

        if (! $lock->get()) {
            return [
                'anchor_id' => $anchorId,
                'state' => 'submission_in_progress',
                'transaction_hash' => $anchor->transaction_hash,
                'message' => 'Another worker is processing this anchor. Check its status before trying again.',
            ];
        }

        $claimToken = null;

        try {
            $freshAnchor = BlockchainAnchor::query()->findOrFail($anchorId);

            // Never start a new submission if a transaction hash already exists.
            if (! empty($freshAnchor->transaction_hash)) {
                return [
                    'anchor_id' => $anchorId,
                    'state' => 'reconciliation_required',
                    'transaction_hash' => $freshAnchor->transaction_hash,
                    'message' => 'A previous transaction hash exists. Reconcile it before attempting another submission.',
                ];
            }

            if (! in_array($freshAnchor->status, [
                BlockchainAnchor::STATUS_PENDING,
                BlockchainAnchor::STATUS_FAILED,
            ], true)) {
                throw new RuntimeException(
                    'Only pending or failed anchors without a transaction hash may be submitted.'
                );
            }

            $claimToken = (string) Str::uuid();

            $claimed = BlockchainAnchor::query()
                ->whereKey($anchorId)
                ->whereIn('status', [
                    BlockchainAnchor::STATUS_PENDING,
                    BlockchainAnchor::STATUS_FAILED,
                ])
                ->whereNull('transaction_hash')
                ->whereNull('submission_claim_token')
                ->update([
                    'submission_claim_token' => $claimToken,
                    'submission_claimed_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($claimed !== 1) {
                return [
                    'anchor_id' => $anchorId,
                    'state' => 'submission_in_progress',
                    'transaction_hash' => null,
                    'message' => 'Another worker owns the submission claim, or the anchor state changed. Check its status before trying again.',
                ];
            }

            return $this->submitAnchorOnLocalHardhatLocked($freshAnchor);
        } finally {
            if ($claimToken !== null) {
                BlockchainAnchor::query()
                    ->whereKey($anchorId)
                    ->where('submission_claim_token', $claimToken)
                    ->update([
                        'submission_claim_token' => null,
                        'submission_claimed_at' => null,
                        'updated_at' => now(),
                    ]);
            }

            $lock->release();
        }
    }

    private function submitAnchorOnLocalHardhatLocked(BlockchainAnchor $anchor): array
    {
        if (! $anchor->exists) {
            throw new RuntimeException(
                'A persisted blockchain anchor is required.'
            );
        }

        $this->assertReady('hardhat-local');

        $rpcUrl = (string) config('blockchain.hardhat_local.rpc_url');
        $network = (string) config('blockchain.network');
        $configuredChainId = (int) config('blockchain.hardhat_local.chain_id');
        $signer = strtolower(
            (string) config('blockchain.hardhat_local.signer_address')
        );
        $contract = strtolower(
            (string) config('blockchain.hardhat_local.contract_address')
        );
        $privateKey = (string) config('blockchain.hardhat_local.private_key');

        if (
            $network !== 'hardhat-local'
            || $configuredChainId !== 31337
            || ! in_array($rpcUrl, [
                'http://127.0.0.1:8545',
                'http://localhost:8545',
                'http://[::1]:8545',
            ], true)
            || $signer !== '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266'
        ) {
            throw new RuntimeException(
                'Transaction submission is restricted to the configured local Hardhat test account and RPC.'
            );
        }

        if ((int) $anchor->chain_id !== 31337) {
            throw new RuntimeException(
                'The anchor is not assigned to local Hardhat chain 31337.'
            );
        }

        if (
            strtolower((string) $anchor->network) !== 'hardhat-local'
            || strtolower((string) $anchor->contract_address) !== $contract
        ) {
            throw new RuntimeException(
                'The anchor network or contract address does not match the local configuration.'
            );
        }

        if (! in_array($anchor->status, [
            BlockchainAnchor::STATUS_PENDING,
            BlockchainAnchor::STATUS_FAILED,
        ], true)) {
            throw new RuntimeException(
                'Only pending or failed anchors may be submitted.'
            );
        }

        if (! empty($anchor->transaction_hash)) {
            return [
                'anchor_id' => $anchor->id,
                'state' => 'reconciliation_required',
                'transaction_hash' => $anchor->transaction_hash,
                'message' => 'A previous transaction hash exists. Reconcile it before attempting another submission.',
            ];
        }

        if (! preg_match('/^[a-fA-F0-9]{64}$/', (string) $anchor->proof_hash)) {
            throw new RuntimeException('The stored proof hash is invalid.');
        }

        $expectedType = match ($anchor->proof_type) {
            BlockchainAnchor::TYPE_DOCUMENT => 1,
            BlockchainAnchor::TYPE_PAYMENT => 2,
            default => throw new RuntimeException('The proof type is invalid.'),
        };

        if ($anchor->proof_type === BlockchainAnchor::TYPE_DOCUMENT) {
            $source = $this->findDocumentFile(
                (int) $anchor->document_file_id
            );
            $prepared = $this->prepareDocumentProof($source);
        } else {
            $source = DocumentPayment::findOrFail($anchor->document_payment_id);
            $prepared = $this->preparePaymentProof($source);
        }

        if (! hash_equals(
            strtolower((string) $anchor->proof_hash),
            strtolower((string) $prepared['proof_hash'])
        )) {
            $anchor->forceFill([
                'status' => BlockchainAnchor::STATUS_MISMATCH,
                'last_error' => 'The source record no longer matches the stored proof hash.',
            ])->save();

            throw new RuntimeException(
                'Source proof hash mismatch. No blockchain transaction was submitted.'
            );
        }

        $proofId = $this->proofIdForAnchor($anchor);
        $proofHash = '0x' . strtolower($anchor->proof_hash);

        // Reconcile an earlier successful submission before attempting another.
        $existingProof = $this->getProof($proofId);

        if ($existingProof['exists']) {
            if (
                strtolower($existingProof['proof_hash']) !== $proofHash
                || $existingProof['proof_type'] !== $expectedType
            ) {
                $anchor->forceFill([
                    'status' => BlockchainAnchor::STATUS_MISMATCH,
                    'last_error' => 'An on-chain proof exists with a different hash or proof type.',
                ])->save();

                throw new RuntimeException(
                    'An on-chain proof exists but does not match this anchor.'
                );
            }

            // A matching on-chain proof is not enough to confirm transaction
            // provenance. Require the original transaction hash for receipt
            // and ProofAnchored-event validation.
            if (empty($anchor->transaction_hash)) {
                return [
                    'anchor_id' => $anchor->id,
                    'state' => 'reconciliation_required',
                    'proof_id' => $proofId,
                    'transaction_hash' => null,
                    'message' => 'A matching proof exists on-chain, but its original transaction hash is missing.',
                ];
            }

            $receipt = $this->getTransactionReceipt(
                $anchor->transaction_hash
            );

            if ($receipt === null) {
                return [
                    'anchor_id' => $anchor->id,
                    'state' => 'pending_receipt',
                    'proof_id' => $proofId,
                    'transaction_hash' => $anchor->transaction_hash,
                ];
            }

            if (
                strtolower((string) ($receipt['transactionHash'] ?? ''))
                    !== strtolower((string) $anchor->transaction_hash)
                || strtolower((string) ($receipt['to'] ?? ''))
                    !== $contract
                || ($receipt['status'] ?? null) !== '0x1'
            ) {
                $anchor->forceFill([
                    'status' => BlockchainAnchor::STATUS_MISMATCH,
                    'last_error' => 'The stored transaction receipt does not match the expected successful contract transaction.',
                ])->save();

                throw new RuntimeException(
                    'The stored transaction receipt is invalid for this anchor.'
                );
            }

            if (! $this->validateProofAnchoredEvent($receipt, $anchor)) {
                $anchor->forceFill([
                    'status' => BlockchainAnchor::STATUS_MISMATCH,
                    'last_error' => 'The stored transaction receipt does not contain the expected ProofAnchored event.',
                ])->save();

                throw new RuntimeException(
                    'The stored transaction receipt does not prove this anchor was created.'
                );
            }

            $inspection = $this->inspectAnchorOnChain($anchor);

            if ($inspection['state'] !== 'matching') {
                $anchor->forceFill([
                    'status' => BlockchainAnchor::STATUS_MISMATCH,
                    'last_error' => 'The existing on-chain proof failed verification.',
                ])->save();

                throw new RuntimeException(
                    'The existing on-chain proof does not match this anchor.'
                );
            }

            $blockHex = $receipt['blockNumber'] ?? null;

            if (
                ! is_string($blockHex)
                || ! preg_match('/^0x[0-9a-fA-F]+$/', $blockHex)
            ) {
                throw new RuntimeException(
                    'The transaction receipt has an invalid block number.'
                );
            }

            $anchor->forceFill([
                'status' => BlockchainAnchor::STATUS_CONFIRMED,
                'block_number' => hexdec($blockHex),
                'last_error' => null,
                'confirmed_at' => $anchor->confirmed_at ?? now(),
            ])->save();

            return [
                'anchor_id' => $anchor->id,
                'state' => 'already_anchored',
                'proof_id' => $proofId,
                'transaction_hash' => $anchor->transaction_hash,
                'block_number' => hexdec($blockHex),
            ];
        }

        // ABI encoding for anchorProof(bytes32,bytes32,uint8).
        $data = '0xc3e6a0dc'
            . substr($proofId, 2)
            . substr($proofHash, 2)
            . str_pad(dechex($expectedType), 64, '0', STR_PAD_LEFT);

        $transactionParameters = $this->prepareLocalHardhatTransaction(
            $signer,
            $contract,
            $data,
            $privateKey
        );

        $rawTransaction = $transactionParameters['raw_transaction'];
        $transactionHash = $transactionParameters['transaction_hash'];

        if (
            ! is_string($rawTransaction)
            || ! preg_match('/^0x(?:[a-fA-F0-9]{2})+$/', $rawTransaction)
            || ! preg_match('/^0x[a-fA-F0-9]{64}$/', $transactionHash)
        ) {
            throw new RuntimeException(
                'The signed local transaction has an invalid format.'
            );
        }

        // Persist the deterministic hash BEFORE broadcasting.
        $anchor->forceFill([
            'status' => BlockchainAnchor::STATUS_SUBMITTED,
            'transaction_hash' => strtolower($transactionHash),
            'attempts' => (int) $anchor->attempts + 1,
            'submitted_at' => now(),
            'last_error' => null,
            'submission_claim_token' => null,
            'submission_claimed_at' => null,
        ])->save();

        try {
            $rpcTransactionHash = $this->rpcForNetwork(
                'hardhat-local',
                'eth_sendRawTransaction',
                [$rawTransaction]
            );
        } catch (\Throwable $exception) {
            $anchor->forceFill([
                'last_error' => 'The broadcast result is uncertain. Reconcile the stored transaction hash before retrying.',
            ])->save();

            return [
                'anchor_id' => $anchor->id,
                'state' => 'reconciliation_required',
                'proof_id' => $proofId,
                'transaction_hash' => strtolower($transactionHash),
                'message' => 'The RPC response was uncertain. Check the transaction hash before taking further action.',
            ];
        }

        if (
            ! is_string($rpcTransactionHash)
            || ! preg_match('/^0x[a-fA-F0-9]{64}$/', $rpcTransactionHash)
            || ! hash_equals(
                strtolower($transactionHash),
                strtolower($rpcTransactionHash)
            )
        ) {
            $anchor->forceFill([
                'last_error' => 'The RPC response did not match the locally calculated transaction hash. Reconciliation is required.',
            ])->save();

            return [
                'anchor_id' => $anchor->id,
                'state' => 'reconciliation_required',
                'proof_id' => $proofId,
                'transaction_hash' => strtolower($transactionHash),
                'message' => 'The RPC returned an unexpected transaction hash. Reconciliation is required.',
            ];
        }

        return [
            'anchor_id' => $anchor->id,
            'state' => 'submitted',
            'proof_id' => $proofId,
            'transaction_hash' => strtolower($transactionHash),
        ];
    }

    /**
     * Validate a persisted anchor before preparing a Sepolia submission.
     *
     * This method does not submit a blockchain transaction.
     */
    public function validateSepoliaAnchorForSubmission(
        BlockchainAnchor $anchor
    ): array {
        if (! $anchor->exists) {
            throw new RuntimeException(
                'A persisted blockchain anchor is required.'
            );
        }

        $this->assertReady('sepolia');

        $metadata = $this->sepoliaAnchorMetadata();

        if (
            $anchor->network !== 'sepolia'
            || (int) $anchor->chain_id !== $metadata['chain_id']
            || strtolower((string) $anchor->contract_address)
                !== strtolower($metadata['contract_address'])
        ) {
            throw new RuntimeException(
                'The anchor network, chain ID, or contract does not match Sepolia configuration.'
            );
        }

        if (! in_array($anchor->status, [
            BlockchainAnchor::STATUS_PENDING,
            BlockchainAnchor::STATUS_FAILED,
        ], true)) {
            throw new RuntimeException(
                'Only pending or failed Sepolia anchors may be submitted.'
            );
        }

        if (! preg_match('/^[a-fA-F0-9]{64}$/', (string) $anchor->proof_hash)) {
            throw new RuntimeException('The stored proof hash is invalid.');
        }

        $expectedType = match ($anchor->proof_type) {
            BlockchainAnchor::TYPE_DOCUMENT => 1,
            BlockchainAnchor::TYPE_PAYMENT => 2,
            default => throw new RuntimeException('The proof type is invalid.'),
        };

        if ($anchor->proof_type === BlockchainAnchor::TYPE_DOCUMENT) {
            $source = $this->findDocumentFile((int) $anchor->document_file_id);
            $prepared = $this->prepareDocumentProof($source);
        } else {
            $source = DocumentPayment::findOrFail(
                (int) $anchor->document_payment_id
            );
            $prepared = $this->preparePaymentProof($source);
        }

        if (! hash_equals(
            strtolower((string) $anchor->proof_hash),
            strtolower((string) $prepared['proof_hash'])
        )) {
            $anchor->forceFill([
                'status' => BlockchainAnchor::STATUS_MISMATCH,
                'last_error' => 'The source record no longer matches the stored proof hash.',
            ])->save();

            throw new RuntimeException(
                'Source proof hash mismatch. No blockchain transaction was submitted.'
            );
        }

        $privateKey = (string) config('blockchain.sepolia.private_key');

        if (str_starts_with(strtolower($privateKey), '0x')) {
            $privateKey = substr($privateKey, 2);
        }

        if (strlen($privateKey) !== 64 || ! ctype_xdigit($privateKey)) {
            throw new RuntimeException(
                'Sepolia private key configuration is invalid.'
            );
        }

        $signer = (string) config('blockchain.sepolia.signer_address');

        if (! preg_match('/^0x[a-fA-F0-9]{40}$/', $signer)) {
            throw new RuntimeException(
                'The configured Sepolia signer address is invalid.'
            );
        }

        return [
            'anchor_id' => $anchor->id,
            'proof_id' => $this->proofIdForAnchor($anchor),
            'proof_hash' => '0x' . strtolower($anchor->proof_hash),
            'proof_type' => $expectedType,
            'network' => 'sepolia',
            'chain_id' => $metadata['chain_id'],
            'contract_address' => $metadata['contract_address'],
            'signer_address' => strtolower($signer),
            'source_proof_valid' => true,
            'transaction_submitted' => false,
        ];
    }

    /**
     * Validate a Mainnet anchor before any transaction is signed or submitted.
     *
     * This method performs validation only; it never broadcasts a transaction.
     */
    public function validateMainnetAnchorForSubmission(
        BlockchainAnchor $anchor
    ): array {
        if (! $anchor->exists) {
            throw new RuntimeException(
                'A persisted blockchain anchor is required.'
            );
        }

        $this->assertReady('mainnet');

        $metadata = $this->mainnetAnchorMetadata();

        if (
            $anchor->network !== 'mainnet'
            || (int) $anchor->chain_id !== $metadata['chain_id']
            || strtolower((string) $anchor->contract_address)
                !== strtolower($metadata['contract_address'])
        ) {
            throw new RuntimeException(
                'The anchor network, chain ID, or contract does not match Mainnet configuration.'
            );
        }

        if (! in_array($anchor->status, [
            BlockchainAnchor::STATUS_PENDING,
            BlockchainAnchor::STATUS_FAILED,
        ], true)) {
            throw new RuntimeException(
                'Only pending or failed Mainnet anchors may be submitted.'
            );
        }

        // A transaction hash must never be overwritten or resubmitted blindly.
        if (! empty($anchor->transaction_hash)) {
            throw new RuntimeException(
                'This anchor already has a transaction hash and must be reconciled.'
            );
        }

        if (! preg_match('/^[a-fA-F0-9]{64}$/', (string) $anchor->proof_hash)) {
            throw new RuntimeException('The stored proof hash is invalid.');
        }

        $expectedType = match ($anchor->proof_type) {
            BlockchainAnchor::TYPE_DOCUMENT => 1,
            BlockchainAnchor::TYPE_PAYMENT => 2,
            default => throw new RuntimeException('The proof type is invalid.'),
        };

        // Recompute the source proof so stale or changed records cannot be anchored.
        if ($anchor->proof_type === BlockchainAnchor::TYPE_DOCUMENT) {
            if (empty($anchor->document_file_id)) {
                throw new RuntimeException(
                    'The document anchor has no source document.'
                );
            }

            $source = $this->findDocumentFile(
                (int) $anchor->document_file_id
            );

            $prepared = $this->prepareDocumentProof($source);
        } else {
            if (empty($anchor->document_payment_id)) {
                throw new RuntimeException(
                    'The payment anchor has no source payment.'
                );
            }

            $source = DocumentPayment::findOrFail(
                (int) $anchor->document_payment_id
            );

            $prepared = $this->preparePaymentProof($source);
        }

        if (
            ! isset($prepared['proof_hash'])
            || ! is_string($prepared['proof_hash'])
            || ! preg_match('/^[a-fA-F0-9]{64}$/', $prepared['proof_hash'])
            || ! hash_equals(
                strtolower((string) $anchor->proof_hash),
                strtolower($prepared['proof_hash'])
            )
        ) {
            $anchor->forceFill([
                'status' => BlockchainAnchor::STATUS_MISMATCH,
                'last_error' =>
                    'The source record no longer matches the stored proof hash.',
            ])->save();

            throw new RuntimeException(
                'Source proof hash mismatch. No blockchain transaction was submitted.'
            );
        }

        $privateKey = (string) config('blockchain.mainnet.private_key');

        if (str_starts_with(strtolower($privateKey), '0x')) {
            $privateKey = substr($privateKey, 2);
        }

        if (strlen($privateKey) !== 64 || ! ctype_xdigit($privateKey)) {
            throw new RuntimeException(
                'Mainnet private key configuration is invalid.'
            );
        }

        $signer = (string) config('blockchain.mainnet.signer_address');

        if (! preg_match('/^0x[a-fA-F0-9]{40}$/', $signer)) {
            throw new RuntimeException(
                'The configured Mainnet signer address is invalid.'
            );
        }

        // Fail closed unless this signer is authorized by the deployed contract.
        if (! $this->isWriter($signer, 'mainnet')) {
            throw new RuntimeException(
                'The configured Mainnet signer is not an authorized contract writer.'
            );
        }

        return [
            'anchor_id' => $anchor->id,
            'proof_id' => $this->proofIdForAnchor($anchor),
            'proof_hash' => '0x' . strtolower($anchor->proof_hash),
            'proof_type' => $expectedType,
            'network' => 'mainnet',
            'chain_id' => $metadata['chain_id'],
            'contract_address' => $metadata['contract_address'],
            'signer_address' => strtolower($signer),
            'source_proof_valid' => true,
            'transaction_submitted' => false,
        ];
    }

    /**
     * Submit a proof anchor to Sepolia using a locally signed EIP-1559 transaction.
     *
     * IMPORTANT: Calling this method can broadcast a real Sepolia transaction.
     * Do not invoke it until an authorized operator explicitly approves submission.
     */
    
    public function submitAnchorOnSepolia(
        BlockchainAnchor $anchor,
        string $operatorConfirmation
    ): array
    {
        if (! $anchor->exists || ! $anchor->getKey()) {
            throw new RuntimeException(
                'A persisted blockchain anchor is required.'
            );
        }

        $expectedConfirmation = 'SUBMIT-SEPOLIA-ANCHOR:' . $anchor->getKey();

        if (! hash_equals($expectedConfirmation, trim($operatorConfirmation))) {
            throw new RuntimeException(
                'Explicit operator confirmation for this anchor is required.'
            );
        }

        if (! config('blockchain.enabled', false)) {
            throw new RuntimeException(
                'Blockchain integration is disabled. Sepolia submission is not allowed.'
            );
        }

        if (config('blockchain.network') !== 'sepolia') {
            throw new RuntimeException(
                'The configured blockchain network must be sepolia before submission.'
            );
        }

        if ($anchor->network !== 'sepolia') {
            throw new RuntimeException(
                'Only Sepolia anchors may be submitted by this method.'
            );
        }

        return $this->submitAnchorOnSepoliaWithLock($anchor);
    }

    private function submitAnchorOnSepoliaWithLock(
        BlockchainAnchor $anchor
    ): array {
        $anchorId = $anchor->getKey();

        $lock = Cache::store()->getStore()->lock(
            'blockchain:sepolia:anchor:' . $anchorId,
            600
        );

        if (! $lock->get()) {
            return [
                'anchor_id' => $anchorId,
                'state' => 'submission_in_progress',
                'transaction_hash' => $anchor->transaction_hash,
                'message' => 'Another worker is processing this anchor. Check its status before trying again.',
            ];
        }

        $claimToken = null;

        try {
            $freshAnchor = BlockchainAnchor::query()->findOrFail($anchorId);

            if (
                in_array($freshAnchor->status, [
                    BlockchainAnchor::STATUS_PENDING,
                    BlockchainAnchor::STATUS_FAILED,
                ], true)
                && empty($freshAnchor->transaction_hash)
            ) {
                $claimToken = (string) Str::uuid();
                $staleClaimBefore = now()->subMinutes(15);

                $claimed = BlockchainAnchor::query()
                    ->whereKey($anchorId)
                    ->where('network', 'sepolia')
                    ->where('chain_id', 11155111)
                    ->whereIn('status', [
                        BlockchainAnchor::STATUS_PENDING,
                        BlockchainAnchor::STATUS_FAILED,
                    ])
                    ->whereNull('transaction_hash')
                    ->where(function ($query) use ($staleClaimBefore) {
                        $query
                            ->whereNull('submission_claim_token')
                            ->orWhereNull('submission_claimed_at')
                            ->orWhere(
                                'submission_claimed_at',
                                '<=',
                                $staleClaimBefore
                            );
                    })
                    ->update([
                        'submission_claim_token' => $claimToken,
                        'submission_claimed_at' => now(),
                        'updated_at' => now(),
                    ]);

                if ($claimed !== 1) {
                    $freshAnchor->refresh();

                    if (
                        empty($freshAnchor->transaction_hash)
                        && ! in_array($freshAnchor->status, [
                            BlockchainAnchor::STATUS_SUBMITTED,
                            BlockchainAnchor::STATUS_CONFIRMED,
                        ], true)
                    ) {
                        return [
                            'anchor_id' => $anchorId,
                            'state' => 'submission_in_progress',
                            'transaction_hash' => null,
                            'message' => 'Another worker owns the submission claim, or the anchor state changed. Check its status before trying again.',
                        ];
                    }
                }
            }

            return $this->submitAnchorOnSepoliaLocked($freshAnchor);
        } finally {
            if ($claimToken !== null) {
                BlockchainAnchor::query()
                    ->whereKey($anchorId)
                    ->where('submission_claim_token', $claimToken)
                    ->update([
                        'submission_claim_token' => null,
                        'submission_claimed_at' => null,
                        'updated_at' => now(),
                    ]);
            }

            $lock->release();
        }
    }

    private function submitAnchorOnMainnetWithLock(
        BlockchainAnchor $anchor
    ): array {
        $anchorId = $anchor->getKey();

        $lock = Cache::store()->getStore()->lock(
            'blockchain:mainnet:anchor:' . $anchorId,
            600
        );

        if (! $lock->get()) {
            return [
                'anchor_id' => $anchorId,
                'state' => 'submission_in_progress',
                'transaction_hash' => $anchor->transaction_hash,
                'message' => 'Another worker is processing this anchor. Check its status before trying again.',
            ];
        }

        $claimToken = null;

        try {
            $freshAnchor = BlockchainAnchor::query()->findOrFail($anchorId);

            if (
                in_array($freshAnchor->status, [
                    BlockchainAnchor::STATUS_PENDING,
                    BlockchainAnchor::STATUS_FAILED,
                ], true)
                && empty($freshAnchor->transaction_hash)
            ) {
                $claimToken = (string) Str::uuid();
                $staleClaimBefore = now()->subMinutes(15);

                $claimed = BlockchainAnchor::query()
                    ->whereKey($anchorId)
                    ->where('network', 'mainnet')
                    ->where('chain_id', 1)
                    ->whereIn('status', [
                        BlockchainAnchor::STATUS_PENDING,
                        BlockchainAnchor::STATUS_FAILED,
                    ])
                    ->whereNull('transaction_hash')
                    ->where(function ($query) use ($staleClaimBefore) {
                        $query
                            ->whereNull('submission_claim_token')
                            ->orWhereNull('submission_claimed_at')
                            ->orWhere(
                                'submission_claimed_at',
                                '<=',
                                $staleClaimBefore
                            );
                    })
                    ->update([
                        'submission_claim_token' => $claimToken,
                        'submission_claimed_at' => now(),
                        'updated_at' => now(),
                    ]);

                if ($claimed !== 1) {
                    $freshAnchor->refresh();

                    if (
                        empty($freshAnchor->transaction_hash)
                        && ! in_array($freshAnchor->status, [
                            BlockchainAnchor::STATUS_SUBMITTED,
                            BlockchainAnchor::STATUS_CONFIRMED,
                        ], true)
                    ) {
                        return [
                            'anchor_id' => $anchorId,
                            'state' => 'submission_in_progress',
                            'transaction_hash' => null,
                            'message' => 'Another worker owns the submission claim, or the anchor state changed. Check its status before trying again.',
                        ];
                    }
                }
            }

            return $this->submitAnchorOnMainnetLocked($freshAnchor);
        } finally {
            if ($claimToken !== null) {
                BlockchainAnchor::query()
                    ->whereKey($anchorId)
                    ->where('submission_claim_token', $claimToken)
                    ->update([
                        'submission_claim_token' => null,
                        'submission_claimed_at' => null,
                        'updated_at' => now(),
                    ]);
            }

            $lock->release();
        }
    }

    private function submitAnchorOnSepoliaLocked(BlockchainAnchor $anchor): array
    {
        if (! $anchor->exists) {
            throw new RuntimeException(
                'A persisted blockchain anchor is required.'
            );
        }

        if ($anchor->network !== 'sepolia') {
            throw new RuntimeException(
                'Only Sepolia anchors may be submitted by this method.'
            );
        }

        // Reconcile an earlier submission instead of creating another transaction.
        if (in_array($anchor->status, [
            BlockchainAnchor::STATUS_SUBMITTED,
            BlockchainAnchor::STATUS_CONFIRMED,
        ], true)) {
            if (empty($anchor->transaction_hash)) {
                return [
                    'anchor_id' => $anchor->id,
                    'state' => 'reconciliation_required',
                    'transaction_hash' => null,
                    'message' => 'The anchor has no transaction hash. Manual reconciliation is required.',
                ];
            }

            return $this->confirmAnchor($anchor);
        }

        if ($anchor->status === BlockchainAnchor::STATUS_MISMATCH) {
            throw new RuntimeException(
                'A mismatched blockchain anchor cannot be submitted.'
            );
        }

        $validated = $this->validateSepoliaAnchorForSubmission($anchor);

        // Never automatically resubmit an anchor that already has a transaction
        // hash, even if its status was reset or marked failed.
        if (! empty($anchor->transaction_hash)) {
            return [
                'anchor_id' => $anchor->id,
                'state' => 'reconciliation_required',
                'transaction_hash' => $anchor->transaction_hash,
                'message' => 'A previous transaction hash exists. Reconcile it before attempting another submission.',
            ];
        }

        $proofId = $validated['proof_id'];
        $proofHash = $validated['proof_hash'];
        $proofType = $validated['proof_type'];
        $contract = strtolower($validated['contract_address']);
        $signer = strtolower($validated['signer_address']);

        // Check whether an earlier transaction already created this proof.
        $existingProof = $this->getProof($proofId, 'sepolia');

        if ($existingProof['exists']) {
            if (
                strtolower((string) $existingProof['proof_hash'])
                    !== strtolower($proofHash)
                || (int) $existingProof['proof_type'] !== $proofType
            ) {
                $anchor->forceFill([
                    'status' => BlockchainAnchor::STATUS_MISMATCH,
                    'last_error' => 'An on-chain proof exists with a different hash or proof type.',
                ])->save();

                throw new RuntimeException(
                    'An on-chain proof exists but does not match this anchor.'
                );
            }

            return [
                'anchor_id' => $anchor->id,
                'state' => 'reconciliation_required',
                'proof_id' => $proofId,
                'transaction_hash' => null,
                'message' => 'A matching proof exists on-chain, but its original transaction hash must be reconciled.',
            ];
        }

        // ABI encoding for anchorProof(bytes32,bytes32,uint8).
        $data = '0xc3e6a0dc'
            . substr($proofId, 2)
            . substr($proofHash, 2)
            . str_pad(dechex($proofType), 64, '0', STR_PAD_LEFT);

        // Obtain nonce, gas limit, and fee parameters without broadcasting.
        $transactionParameters = $this->prepareSepoliaTransaction(
            $signer,
            $contract,
            $data
        );

        $privateKey = (string) config('blockchain.sepolia.private_key');

        if (str_starts_with(strtolower($privateKey), '0x')) {
            $privateKey = substr($privateKey, 2);
        }

        if (strlen($privateKey) !== 64 || ! ctype_xdigit($privateKey)) {
            throw new RuntimeException(
                'Sepolia private key configuration is invalid.'
            );
        }

        $transaction = new EIP1559Transaction($transactionParameters);
        $rawTransaction = $transaction->sign('0x' . $privateKey);

        if (
            ! is_string($rawTransaction)
            || ! preg_match('/^02(?:[a-fA-F0-9]{2})+$/', $rawTransaction)
        ) {
            throw new RuntimeException(
                'The locally signed Sepolia transaction has an invalid format.'
            );
        }

        // Ensure the private key actually corresponds to the configured signer.
        $recoveredSigner = strtolower($transaction->getFromAddress());

        if (! hash_equals($signer, $recoveredSigner)) {
            throw new RuntimeException(
                'The signing key does not match the configured Sepolia signer.'
            );
        }

        $rawBytes = hex2bin($rawTransaction);

        if ($rawBytes === false) {
            throw new RuntimeException(
                'The signed Sepolia transaction could not be decoded.'
            );
        }

        $util = new Util();
        $transactionHash = '0x' . strtolower($util->sha3($rawBytes));

        if (! preg_match('/^0x[a-f0-9]{64}$/', $transactionHash)) {
            throw new RuntimeException(
                'The locally calculated Sepolia transaction hash is invalid.'
            );
        }

        // Persist the deterministic hash BEFORE attempting broadcast. If the RPC
        // times out after accepting the transaction, we can reconcile by this hash.
        $anchor->forceFill([
            'status' => BlockchainAnchor::STATUS_SUBMITTED,
            'transaction_hash' => $transactionHash,
            'attempts' => (int) $anchor->attempts + 1,
            'submitted_at' => now(),
            'last_error' => null,
            'submission_claim_token' => null,
            'submission_claimed_at' => null,
        ])->save();

        try {
            $rpcTransactionHash = $this->rpcForNetwork(
                'sepolia',
                'eth_sendRawTransaction',
                ['0x' . $rawTransaction]
            );
        } catch (\Throwable $exception) {
            // Do not clear the hash or automatically retry: the RPC may have
            // accepted the transaction even though its response was lost.
            $anchor->forceFill([
                'last_error' => 'The broadcast result is uncertain. Reconcile the stored transaction hash before retrying.',
            ])->save();

            return [
                'anchor_id' => $anchor->id,
                'state' => 'reconciliation_required',
                'proof_id' => $proofId,
                'transaction_hash' => $transactionHash,
                'message' => 'The RPC response was uncertain. Check the transaction hash before taking further action.',
            ];
        }

        if (
            ! is_string($rpcTransactionHash)
            || ! preg_match('/^0x[a-fA-F0-9]{64}$/', $rpcTransactionHash)
            || ! hash_equals(
                strtolower($transactionHash),
                strtolower($rpcTransactionHash)
            )
        ) {
            $anchor->forceFill([
                'last_error' => 'The RPC response did not match the locally calculated transaction hash. Reconciliation is required.',
            ])->save();

            return [
                'anchor_id' => $anchor->id,
                'state' => 'reconciliation_required',
                'proof_id' => $proofId,
                'transaction_hash' => $transactionHash,
                'message' => 'The RPC transaction hash did not match. Reconcile the stored hash before retrying.',
            ];
        }

        return [
            'anchor_id' => $anchor->id,
            'state' => 'submitted',
            'proof_id' => $proofId,
            'transaction_hash' => $transactionHash,
        ];
    }

    private function submitAnchorOnMainnetLocked(BlockchainAnchor $anchor): array
    {
        if (! $anchor->exists) {
            throw new RuntimeException(
                'A persisted blockchain anchor is required.'
            );
        }

        if ($anchor->network !== 'mainnet') {
            throw new RuntimeException(
                'Only Mainnet anchors may be submitted by this method.'
            );
        }

        if (in_array($anchor->status, [
            BlockchainAnchor::STATUS_SUBMITTED,
            BlockchainAnchor::STATUS_CONFIRMED,
        ], true)) {
            if (empty($anchor->transaction_hash)) {
                return [
                    'anchor_id' => $anchor->id,
                    'state' => 'reconciliation_required',
                    'transaction_hash' => null,
                    'message' => 'The anchor has no transaction hash. Manual reconciliation is required.',
                ];
            }

            return $this->confirmAnchor($anchor);
        }

        if ($anchor->status === BlockchainAnchor::STATUS_MISMATCH) {
            throw new RuntimeException(
                'A mismatched blockchain anchor cannot be submitted.'
            );
        }

        // A stored hash always takes precedence over creating another transaction.
        if (! empty($anchor->transaction_hash)) {
            return [
                'anchor_id' => $anchor->id,
                'state' => 'reconciliation_required',
                'transaction_hash' => $anchor->transaction_hash,
                'message' => 'A previous transaction hash exists. Reconcile it before attempting another submission.',
            ];
        }

        $validated = $this->validateMainnetAnchorForSubmission($anchor);

        $proofId = $validated['proof_id'];
        $proofHash = $validated['proof_hash'];
        $proofType = $validated['proof_type'];
        $contract = strtolower($validated['contract_address']);
        $signer = strtolower($validated['signer_address']);

        // Check whether this proof was already written by an earlier transaction.
        $existingProof = $this->getProof($proofId, 'mainnet');

        if ($existingProof['exists']) {
            if (
                strtolower((string) $existingProof['proof_hash'])
                    !== strtolower($proofHash)
                || (int) $existingProof['proof_type'] !== $proofType
            ) {
                $anchor->forceFill([
                    'status' => BlockchainAnchor::STATUS_MISMATCH,
                    'last_error' => 'An on-chain proof exists with a different hash or proof type.',
                ])->save();

                throw new RuntimeException(
                    'An on-chain proof exists but does not match this anchor.'
                );
            }

            return [
                'anchor_id' => $anchor->id,
                'state' => 'reconciliation_required',
                'proof_id' => $proofId,
                'transaction_hash' => null,
                'message' => 'A matching proof exists on-chain, but its original transaction hash must be reconciled.',
            ];
        }

        // ABI: anchorProof(bytes32,bytes32,uint8).
        $data = '0xc3e6a0dc'
            . substr($proofId, 2)
            . substr($proofHash, 2)
            . str_pad(dechex($proofType), 64, '0', STR_PAD_LEFT);

        $transactionParameters = $this->prepareMainnetTransaction(
            $signer,
            $contract,
            $data
        );

        $privateKey = (string) config('blockchain.mainnet.private_key');

        if (str_starts_with(strtolower($privateKey), '0x')) {
            $privateKey = substr($privateKey, 2);
        }

        if (strlen($privateKey) !== 64 || ! ctype_xdigit($privateKey)) {
            throw new RuntimeException(
                'Mainnet private key configuration is invalid.'
            );
        }

        $transaction = new EIP1559Transaction($transactionParameters);
        $rawTransaction = $transaction->sign('0x' . $privateKey);

        if (
            ! is_string($rawTransaction)
            || ! preg_match('/^02(?:[a-fA-F0-9]{2})+$/', $rawTransaction)
        ) {
            throw new RuntimeException(
                'The locally signed Mainnet transaction has an invalid format.'
            );
        }

        // Confirm the private key corresponds to the configured signer.
        $recoveredSigner = strtolower($transaction->getFromAddress());

        if (! hash_equals($signer, $recoveredSigner)) {
            throw new RuntimeException(
                'The signing key does not match the configured Mainnet signer.'
            );
        }

        $rawBytes = hex2bin($rawTransaction);

        if ($rawBytes === false) {
            throw new RuntimeException(
                'The signed Mainnet transaction could not be decoded.'
            );
        }

        $util = new Util();
        $transactionHash = '0x' . strtolower($util->sha3($rawBytes));

        if (! preg_match('/^0x[a-f0-9]{64}$/', $transactionHash)) {
            throw new RuntimeException(
                'The locally calculated Mainnet transaction hash is invalid.'
            );
        }

        // Persist the deterministic hash before broadcast to prevent blind retries.
        $anchor->forceFill([
            'status' => BlockchainAnchor::STATUS_SUBMITTED,
            'transaction_hash' => $transactionHash,
            'attempts' => (int) $anchor->attempts + 1,
            'submitted_at' => now(),
            'last_error' => null,
            'submission_claim_token' => null,
            'submission_claimed_at' => null,
        ])->save();

        try {
            $rpcTransactionHash = $this->rpcForNetwork(
                'mainnet',
                'eth_sendRawTransaction',
                ['0x' . $rawTransaction]
            );
        } catch (\Throwable $exception) {
            // The node may have accepted the transaction despite a timeout.
            report($exception);

            $anchor->forceFill([
                'last_error' => 'The broadcast result is uncertain. Reconcile the stored transaction hash before retrying.',
            ])->save();

            return [
                'anchor_id' => $anchor->id,
                'state' => 'reconciliation_required',
                'proof_id' => $proofId,
                'transaction_hash' => $transactionHash,
                'message' => 'The RPC response was uncertain. Check the transaction hash before taking further action.',
            ];
        }

        if (
            ! is_string($rpcTransactionHash)
            || ! preg_match('/^0x[a-fA-F0-9]{64}$/', $rpcTransactionHash)
            || ! hash_equals(
                strtolower($transactionHash),
                strtolower($rpcTransactionHash)
            )
        ) {
            $anchor->forceFill([
                'last_error' => 'The RPC response did not match the locally calculated transaction hash. Reconciliation is required.',
            ])->save();

            return [
                'anchor_id' => $anchor->id,
                'state' => 'reconciliation_required',
                'proof_id' => $proofId,
                'transaction_hash' => $transactionHash,
                'message' => 'The RPC transaction hash did not match. Reconcile the stored hash before retrying.',
            ];
        }

        return [
            'anchor_id' => $anchor->id,
            'state' => 'submitted',
            'proof_id' => $proofId,
            'transaction_hash' => $transactionHash,
        ];
    }

    /**
     * Return validated Hardhat-local deployment metadata for a new anchor.
     *
     * This method does not submit a blockchain transaction.
     */
    private function hardhatAnchorMetadata(): array
    {
        $settings = $this->networkSettings('hardhat-local');

        if ($settings['chain_id'] !== 31337) {
            throw new RuntimeException(
                'The configured Hardhat chain ID must be 31337.'
            );
        }

        return [
            'network' => 'hardhat-local',
            'chain_id' => $settings['chain_id'],
            'contract_address' => $settings['contract_address'],
        ];
    }

    /**
     * Return validated Sepolia deployment metadata for a new anchor.
     *
     * This method does not submit a blockchain transaction.
     */
    private function sepoliaAnchorMetadata(): array
    {
        $network = 'sepolia';
        $chainId = (int) config('blockchain.sepolia.chain_id');
        $contractAddress = (string) config(
            'blockchain.sepolia.contract_address'
        );

        if ($chainId !== 11155111) {
            throw new RuntimeException(
                'The configured Sepolia chain ID is invalid.'
            );
        }

        if (! preg_match('/^0x[a-fA-F0-9]{40}$/', $contractAddress)) {
            throw new RuntimeException(
                'The configured Sepolia contract address is invalid.'
            );
        }

        return [
            'network' => $network,
            'chain_id' => $chainId,
            'contract_address' => $contractAddress,
        ];
    }

    /**
     * Return validated Ethereum Mainnet deployment metadata.
     *
     * This method only prepares metadata for a database anchor.
     * It does not connect to Mainnet or submit a transaction.
     */
    private function mainnetAnchorMetadata(): array
    {
        $chainId = (int) config('blockchain.mainnet.chain_id');
        $contractAddress = (string) config(
            'blockchain.mainnet.contract_address'
        );

        if ($chainId !== 1) {
            throw new RuntimeException(
                'The configured Ethereum Mainnet chain ID must be 1.'
            );
        }

        if (! preg_match('/^0x[a-fA-F0-9]{40}$/', $contractAddress)) {
            throw new RuntimeException(
                'The configured Ethereum Mainnet contract address is invalid.'
            );
        }

        return [
            'network' => 'mainnet',
            'chain_id' => $chainId,
            'contract_address' => $contractAddress,
        ];
    }

    /**
     * Create or retrieve a document blockchain anchor for Hardhat-local.
     *
     * This method only creates the database record.
     * It does not submit a blockchain transaction.
     */
    public function createDocumentAnchor(DocumentFile $file): BlockchainAnchor
    {
        $this->assertBlockchainAnchorCreationEnabled();
        $proof = $this->prepareDocumentProof($file);
        $metadata = $this->hardhatAnchorMetadata();

        return DB::transaction(function () use (
            $proof,
            $metadata
        ): BlockchainAnchor {
            $anchor = BlockchainAnchor::query()
                ->where('document_file_id', $proof['document_file_id'])
                ->where('proof_version', $proof['proof_version'])
                ->where('network', $metadata['network'])
                ->lockForUpdate()
                ->first();

            if ($anchor) {
                if (
                    $anchor->proof_type !== BlockchainAnchor::TYPE_DOCUMENT ||
                    ! hash_equals($anchor->proof_hash, $proof['proof_hash'])
                ) {
                    throw new RuntimeException(
                        'An existing document anchor has a different proof hash.'
                    );
                }

                if (
                    (int) $anchor->chain_id !== $metadata['chain_id'] ||
                    strcasecmp(
                        (string) $anchor->contract_address,
                        $metadata['contract_address']
                    ) !== 0
                ) {
                    throw new RuntimeException(
                        'The existing Hardhat document anchor uses different deployment metadata.'
                    );
                }

                return $anchor;
            }

            return BlockchainAnchor::query()->create([
                'document_file_id' => $proof['document_file_id'],
                'document_payment_id' => null,
                'proof_type' => BlockchainAnchor::TYPE_DOCUMENT,
                'proof_version' => $proof['proof_version'],
                'proof_hash' => $proof['proof_hash'],
                ...$metadata,
                'status' => BlockchainAnchor::STATUS_PENDING,
                'attempts' => 0,
            ]);
        });
    }

    /**
     * Create or retrieve a document anchor specifically for Sepolia.
     *
     * This method only creates the database record.
     * It does not submit a blockchain transaction.
     */
    public function createSepoliaDocumentAnchor(
        DocumentFile $file
    ): BlockchainAnchor {
        $this->assertBlockchainAnchorCreationEnabled();
        $proof = $this->prepareDocumentProof($file);
        $metadata = $this->sepoliaAnchorMetadata();

        return DB::transaction(function () use (
            $proof,
            $metadata
        ): BlockchainAnchor {
            $anchor = BlockchainAnchor::query()
                ->where('document_file_id', $proof['document_file_id'])
                ->where('proof_version', $proof['proof_version'])
                ->where('network', $metadata['network'])
                ->lockForUpdate()
                ->first();

            if ($anchor) {
                if (
                    $anchor->proof_type !== BlockchainAnchor::TYPE_DOCUMENT ||
                    ! hash_equals($anchor->proof_hash, $proof['proof_hash'])
                ) {
                    throw new RuntimeException(
                        'An existing Sepolia document anchor has a different proof hash.'
                    );
                }

                if (
                    (int) $anchor->chain_id !== $metadata['chain_id'] ||
                    strcasecmp(
                        (string) $anchor->contract_address,
                        $metadata['contract_address']
                    ) !== 0
                ) {
                    throw new RuntimeException(
                        'The existing Sepolia document anchor uses different deployment metadata.'
                    );
                }

                return $anchor;
            }

            return BlockchainAnchor::query()->create([
                'document_file_id' => $proof['document_file_id'],
                'document_payment_id' => null,
                'proof_type' => BlockchainAnchor::TYPE_DOCUMENT,
                'proof_version' => $proof['proof_version'],
                'proof_hash' => $proof['proof_hash'],
                ...$metadata,
                'status' => BlockchainAnchor::STATUS_PENDING,
                'attempts' => 0,
            ]);
        });
    }

    /**
     * Create or retrieve an Ethereum Mainnet document anchor.
     *
     * Database operation only. This method never submits a transaction.
     */
    public function createMainnetDocumentAnchor(
        DocumentFile $file
    ): BlockchainAnchor {
        $this->assertBlockchainAnchorCreationEnabled();
        $proof = $this->prepareDocumentProof($file);
        $metadata = $this->mainnetAnchorMetadata();

        return DB::transaction(function () use (
            $proof,
            $metadata
        ): BlockchainAnchor {
            $anchor = BlockchainAnchor::query()
                ->where('document_file_id', $proof['document_file_id'])
                ->where('proof_version', $proof['proof_version'])
                ->where('network', 'mainnet')
                ->lockForUpdate()
                ->first();

            if ($anchor) {
                if (
                    $anchor->proof_type !== BlockchainAnchor::TYPE_DOCUMENT ||
                    ! hash_equals($anchor->proof_hash, $proof['proof_hash'])
                ) {
                    throw new RuntimeException(
                        'An existing Mainnet document anchor has a different proof hash.'
                    );
                }

                if (
                    (int) $anchor->chain_id !== $metadata['chain_id'] ||
                    strcasecmp(
                        (string) $anchor->contract_address,
                        $metadata['contract_address']
                    ) !== 0
                ) {
                    throw new RuntimeException(
                        'The existing Mainnet document anchor uses different deployment metadata.'
                    );
                }

                return $anchor;
            }

            return BlockchainAnchor::query()->create([
                'document_file_id' => $proof['document_file_id'],
                'document_payment_id' => null,
                'proof_type' => BlockchainAnchor::TYPE_DOCUMENT,
                'proof_version' => $proof['proof_version'],
                'proof_hash' => $proof['proof_hash'],
                ...$metadata,
                'status' => BlockchainAnchor::STATUS_PENDING,
                'attempts' => 0,
            ]);
        });
    }

    /**
     * Create or retrieve a payment blockchain anchor for Hardhat-local.
     *
     * This method only creates the database record.
     * It does not submit a blockchain transaction.
     */
    public function createPaymentAnchor(
        DocumentPayment $payment
    ): BlockchainAnchor {
        $this->assertBlockchainAnchorCreationEnabled();
        $proof = $this->preparePaymentProof($payment);
        $metadata = $this->hardhatAnchorMetadata();

        return DB::transaction(function () use (
            $proof,
            $metadata
        ): BlockchainAnchor {
            $anchor = BlockchainAnchor::query()
                ->where('document_payment_id', $proof['payment_id'])
                ->where('proof_version', $proof['proof_version'])
                ->where('network', $metadata['network'])
                ->lockForUpdate()
                ->first();

            if ($anchor) {
                if (
                    $anchor->proof_type !== BlockchainAnchor::TYPE_PAYMENT ||
                    ! hash_equals($anchor->proof_hash, $proof['proof_hash'])
                ) {
                    throw new RuntimeException(
                        'An existing payment anchor has a different proof hash.'
                    );
                }

                if (
                    (int) $anchor->chain_id !== $metadata['chain_id'] ||
                    strcasecmp(
                        (string) $anchor->contract_address,
                        $metadata['contract_address']
                    ) !== 0
                ) {
                    throw new RuntimeException(
                        'The existing Hardhat payment anchor uses different deployment metadata.'
                    );
                }

                return $anchor;
            }

            return BlockchainAnchor::query()->create([
                'document_file_id' => null,
                'document_payment_id' => $proof['payment_id'],
                'proof_type' => BlockchainAnchor::TYPE_PAYMENT,
                'proof_version' => $proof['proof_version'],
                'proof_hash' => $proof['proof_hash'],
                ...$metadata,
                'status' => BlockchainAnchor::STATUS_PENDING,
                'attempts' => 0,
            ]);
        });
    }

    /**
     * Create or retrieve a payment anchor specifically for Sepolia.
     *
     * This method only creates the database record.
     * It does not submit a blockchain transaction.
     */
    public function createSepoliaPaymentAnchor(
        DocumentPayment $payment
    ): BlockchainAnchor {
        $this->assertBlockchainAnchorCreationEnabled();
        $proof = $this->preparePaymentProof($payment);
        $metadata = $this->sepoliaAnchorMetadata();

        return DB::transaction(function () use (
            $proof,
            $metadata
        ): BlockchainAnchor {
            $anchor = BlockchainAnchor::query()
                ->where('document_payment_id', $proof['payment_id'])
                ->where('proof_version', $proof['proof_version'])
                ->where('network', $metadata['network'])
                ->lockForUpdate()
                ->first();

            if ($anchor) {
                if (
                    $anchor->proof_type !== BlockchainAnchor::TYPE_PAYMENT ||
                    ! hash_equals($anchor->proof_hash, $proof['proof_hash'])
                ) {
                    throw new RuntimeException(
                        'An existing Sepolia payment anchor has a different proof hash.'
                    );
                }

                if (
                    (int) $anchor->chain_id !== $metadata['chain_id'] ||
                    strcasecmp(
                        (string) $anchor->contract_address,
                        $metadata['contract_address']
                    ) !== 0
                ) {
                    throw new RuntimeException(
                        'The existing Sepolia payment anchor uses different deployment metadata.'
                    );
                }

                return $anchor;
            }

            return BlockchainAnchor::query()->create([
                'document_file_id' => null,
                'document_payment_id' => $proof['payment_id'],
                'proof_type' => BlockchainAnchor::TYPE_PAYMENT,
                'proof_version' => $proof['proof_version'],
                'proof_hash' => $proof['proof_hash'],
                ...$metadata,
                'status' => BlockchainAnchor::STATUS_PENDING,
                'attempts' => 0,
            ]);
        });
    }

    /**
     * Create or retrieve an Ethereum Mainnet payment anchor.
     *
     * Database operation only. This method never submits a transaction.
     */
    public function createMainnetPaymentAnchor(
        DocumentPayment $payment
    ): BlockchainAnchor {
        $this->assertBlockchainAnchorCreationEnabled();
        $proof = $this->preparePaymentProof($payment);
        $metadata = $this->mainnetAnchorMetadata();

        return DB::transaction(function () use (
            $proof,
            $metadata
        ): BlockchainAnchor {
            $anchor = BlockchainAnchor::query()
                ->where('document_payment_id', $proof['payment_id'])
                ->where('proof_version', $proof['proof_version'])
                ->where('network', 'mainnet')
                ->lockForUpdate()
                ->first();

            if ($anchor) {
                if (
                    $anchor->proof_type !== BlockchainAnchor::TYPE_PAYMENT ||
                    ! hash_equals($anchor->proof_hash, $proof['proof_hash'])
                ) {
                    throw new RuntimeException(
                        'An existing Mainnet payment anchor has a different proof hash.'
                    );
                }

                if (
                    (int) $anchor->chain_id !== $metadata['chain_id'] ||
                    strcasecmp(
                        (string) $anchor->contract_address,
                        $metadata['contract_address']
                    ) !== 0
                ) {
                    throw new RuntimeException(
                        'The existing Mainnet payment anchor uses different deployment metadata.'
                    );
                }

                return $anchor;
            }

            return BlockchainAnchor::query()->create([
                'document_file_id' => null,
                'document_payment_id' => $proof['payment_id'],
                'proof_type' => BlockchainAnchor::TYPE_PAYMENT,
                'proof_version' => $proof['proof_version'],
                'proof_hash' => $proof['proof_hash'],
                ...$metadata,
                'status' => BlockchainAnchor::STATUS_PENDING,
                'attempts' => 0,
            ]);
        });
    }

    /**
     * Prepare validated EIP-1559 transaction parameters for Sepolia.
     *
     * This method only reads RPC data. It does not sign or broadcast.
     */
    private function prepareSepoliaTransaction(
        string $signer,
        string $contract,
        string $data
    ): array {
        if (! preg_match('/^0x[a-fA-F0-9]{40}$/', $signer)) {
            throw new RuntimeException('The Sepolia signer address is invalid.');
        }

        if (! preg_match('/^0x[a-fA-F0-9]{40}$/', $contract)) {
            throw new RuntimeException('The Sepolia contract address is invalid.');
        }

        if (
            ! preg_match('/^0x(?:[a-fA-F0-9]{2})+$/', $data)
            || ! str_starts_with(strtolower($data), '0xc3e6a0dc')
        ) {
            throw new RuntimeException('The Sepolia anchor calldata is invalid.');
        }

        $nonce = $this->parseSepoliaRpcQuantity(
            $this->rpcForNetwork('sepolia', 'eth_getTransactionCount', [
                strtolower($signer),
                'pending',
            ]),
            'nonce'
        );

        $estimatedGas = $this->parseSepoliaRpcQuantity(
            $this->rpcForNetwork('sepolia', 'eth_estimateGas', [[
                'from' => strtolower($signer),
                'to' => strtolower($contract),
                'data' => $data,
                'value' => '0x0',
            ]]),
            'gas estimate'
        );

        if ($estimatedGas <= 0 || $estimatedGas > 1_000_000) {
            throw new RuntimeException(
                'The Sepolia gas estimate is outside the permitted range.'
            );
        }

        // Add a 20% gas-limit buffer, rounding upward.
        $gasLimit = $estimatedGas + intdiv($estimatedGas + 4, 5);

        if ($gasLimit > 1_000_000) {
            throw new RuntimeException(
                'The buffered Sepolia gas limit exceeds the permitted maximum.'
            );
        }

        $priorityFee = $this->parseSepoliaRpcQuantity(
            $this->rpcForNetwork('sepolia', 'eth_maxPriorityFeePerGas'),
            'priority fee'
        );

        // Fee caps are expressed in wei.
        $maxPriorityFee = 5_000_000_000;
        $maxBaseFee = 200_000_000_000;
        $maxTotalFee = 500_000_000_000;

        if ($priorityFee <= 0 || $priorityFee > $maxPriorityFee) {
            throw new RuntimeException(
                'The Sepolia priority fee is outside the permitted range.'
            );
        }

        $feeHistory = $this->rpcForNetwork(
            'sepolia',
            'eth_feeHistory',
            ['0x5', 'latest', [50]]
        );

        if (
            ! is_array($feeHistory)
            || ! isset($feeHistory['baseFeePerGas'])
            || ! is_array($feeHistory['baseFeePerGas'])
            || $feeHistory['baseFeePerGas'] === []
        ) {
            throw new RuntimeException(
                'The Sepolia RPC returned invalid fee history.'
            );
        }

        $baseFees = $feeHistory['baseFeePerGas'];
        $latestBaseFeeHex = end($baseFees);

        $baseFee = $this->parseSepoliaRpcQuantity(
            $latestBaseFeeHex,
            'base fee'
        );

        if ($baseFee <= 0 || $baseFee > $maxBaseFee) {
            throw new RuntimeException(
                'The Sepolia base fee is outside the permitted range.'
            );
        }

        $maxFee = ($baseFee * 2) + $priorityFee;

        if ($maxFee > $maxTotalFee) {
            throw new RuntimeException(
                'The calculated Sepolia maximum fee exceeds the permitted cap.'
            );
        }

        return [
            'chainId' => $this->sepoliaRpcQuantity(11155111),
            'nonce' => $this->sepoliaRpcQuantity($nonce),
            'gas' => $this->sepoliaRpcQuantity($gasLimit),
            'maxPriorityFeePerGas' => $this->sepoliaRpcQuantity($priorityFee),
            'maxFeePerGas' => $this->sepoliaRpcQuantity($maxFee),
            'to' => strtolower($contract),
            'value' => '0x0',
            'data' => $data,
            'accessList' => [],
        ];
    }

    private function prepareMainnetTransaction(
        string $signer,
        string $contract,
        string $data
    ): array {
        if (! preg_match('/^0x[a-fA-F0-9]{40}$/', $signer)) {
            throw new RuntimeException('The Mainnet signer address is invalid.');
        }

        if (! preg_match('/^0x[a-fA-F0-9]{40}$/', $contract)) {
            throw new RuntimeException('The Mainnet contract address is invalid.');
        }

        if (
            ! preg_match('/^0x(?:[a-fA-F0-9]{2})+$/', $data)
            || ! str_starts_with(strtolower($data), '0xc3e6a0dc')
        ) {
            throw new RuntimeException('The Mainnet anchor calldata is invalid.');
        }

        // Fail closed if the RPC is not connected to Ethereum Mainnet.
        $this->assertReady('mainnet');

        $configuredContract = config('blockchain.mainnet.contract_address');

        if (
            ! is_string($configuredContract)
            || ! hash_equals(
                strtolower($configuredContract),
                strtolower($contract)
            )
        ) {
            throw new RuntimeException(
                'The Mainnet transaction contract does not match configuration.'
            );
        }

        $configuredSigner = config('blockchain.mainnet.signer_address');

        if (
            ! is_string($configuredSigner)
            || ! preg_match('/^0x[a-fA-F0-9]{40}$/', $configuredSigner)
            || ! hash_equals(
                strtolower($configuredSigner),
                strtolower($signer)
            )
        ) {
            throw new RuntimeException(
                'The Mainnet transaction signer does not match configuration.'
            );
        }

        // Configuration limits are in whole gwei; RPC fees are in wei.
        $maxPriorityFeeGwei = config(
            'blockchain.mainnet.max_priority_fee_gwei'
        );
        $maxBaseFeeGwei = config(
            'blockchain.mainnet.max_base_fee_gwei'
        );
        $maxTotalFeeGwei = config(
            'blockchain.mainnet.max_total_fee_gwei'
        );

        foreach ([
            'priority fee' => $maxPriorityFeeGwei,
            'base fee' => $maxBaseFeeGwei,
            'total fee' => $maxTotalFeeGwei,
        ] as $label => $value) {
            if (! is_int($value) || $value <= 0 || $value > 1_000_000) {
                throw new RuntimeException(
                    "The configured Mainnet {$label} cap is invalid."
                );
            }
        }

        $maxPriorityFee = $maxPriorityFeeGwei * 1_000_000_000;
        $maxBaseFee = $maxBaseFeeGwei * 1_000_000_000;
        $maxTotalFee = $maxTotalFeeGwei * 1_000_000_000;

        $nonce = $this->parseSepoliaRpcQuantity(
            $this->rpcForNetwork('mainnet', 'eth_getTransactionCount', [
                strtolower($signer),
                'pending',
            ]),
            'nonce'
        );

        $estimatedGas = $this->parseSepoliaRpcQuantity(
            $this->rpcForNetwork('mainnet', 'eth_estimateGas', [[
                'from' => strtolower($signer),
                'to' => strtolower($contract),
                'data' => $data,
                'value' => '0x0',
            ]]),
            'gas estimate'
        );

        if ($estimatedGas <= 0 || $estimatedGas > 1_000_000) {
            throw new RuntimeException(
                'The Mainnet gas estimate is outside the permitted range.'
            );
        }

        // Add a 20% gas-limit buffer, rounding upward.
        $gasLimit = $estimatedGas + intdiv($estimatedGas + 4, 5);

        if ($gasLimit > 1_000_000) {
            throw new RuntimeException(
                'The buffered Mainnet gas limit exceeds the permitted maximum.'
            );
        }

        $priorityFee = $this->parseSepoliaRpcQuantity(
            $this->rpcForNetwork('mainnet', 'eth_maxPriorityFeePerGas'),
            'priority fee'
        );

        if ($priorityFee <= 0 || $priorityFee > $maxPriorityFee) {
            throw new RuntimeException(
                'The Mainnet priority fee is outside the configured cap.'
            );
        }

        $feeHistory = $this->rpcForNetwork(
            'mainnet',
            'eth_feeHistory',
            ['0x5', 'latest', [50]]
        );

        if (
            ! is_array($feeHistory)
            || ! isset($feeHistory['baseFeePerGas'])
            || ! is_array($feeHistory['baseFeePerGas'])
            || $feeHistory['baseFeePerGas'] === []
        ) {
            throw new RuntimeException(
                'The Mainnet RPC returned invalid fee history.'
            );
        }

        $baseFees = $feeHistory['baseFeePerGas'];
        $latestBaseFeeHex = end($baseFees);

        $baseFee = $this->parseSepoliaRpcQuantity(
            $latestBaseFeeHex,
            'base fee'
        );

        if ($baseFee <= 0 || $baseFee > $maxBaseFee) {
            throw new RuntimeException(
                'The Mainnet base fee is outside the configured cap.'
            );
        }

        // EIP-1559 fee cap: 2 × base fee + priority fee.
        $maxFee = ($baseFee * 2) + $priorityFee;

        if ($maxFee > $maxTotalFee) {
            throw new RuntimeException(
                'The calculated Mainnet maximum fee exceeds the configured cap.'
            );
        }

        return [
            'chainId' => $this->sepoliaRpcQuantity(1),
            'nonce' => $this->sepoliaRpcQuantity($nonce),
            'gas' => $this->sepoliaRpcQuantity($gasLimit),
            'maxPriorityFeePerGas' => $this->sepoliaRpcQuantity($priorityFee),
            'maxFeePerGas' => $this->sepoliaRpcQuantity($maxFee),
            'to' => strtolower($contract),
            'value' => '0x0',
            'data' => $data,
            'accessList' => [],
        ];
    }

    /**
     * Prepare a legacy transaction for the local Hardhat network.
     *
     * This method reads blockchain state and signs locally.
     * It does not broadcast a transaction.
     */
    private function prepareLocalHardhatTransaction(
        string $signer,
        string $contract,
        string $data,
        string $privateKey
    ): array {
        $chainId = (int) config('blockchain.hardhat_local.chain_id');

        if ($chainId !== 31337) {
            throw new RuntimeException(
                'Local Hardhat transaction signing requires chain ID 31337.'
            );
        }

        if (
            ! preg_match('/^0x[a-fA-F0-9]{40}$/', $signer)
            || ! preg_match('/^0x[a-fA-F0-9]{40}$/', $contract)
            || ! preg_match('/^0x[a-fA-F0-9]+$/', $data)
            || ! preg_match('/^(0x)?[a-fA-F0-9]{64}$/', $privateKey)
        ) {
            throw new RuntimeException(
                'Invalid local Hardhat transaction parameters.'
            );
        }

        $nonce = $this->parseSepoliaRpcQuantity(
            $this->rpcForNetwork('hardhat-local', 'eth_getTransactionCount', [
                strtolower($signer),
                'pending',
            ]),
            'local nonce'
        );

        $estimatedGas = $this->parseSepoliaRpcQuantity(
            $this->rpcForNetwork('hardhat-local', 'eth_estimateGas', [[
                'from' => strtolower($signer),
                'to' => strtolower($contract),
                'data' => $data,
                'value' => '0x0',
            ]]),
            'local gas estimate'
        );

        if ($estimatedGas <= 0 || $estimatedGas > 1_000_000) {
            throw new RuntimeException(
                'The local Hardhat gas estimate is outside the permitted range.'
            );
        }

        $gasLimit = (int) ceil($estimatedGas * 1.20);

        if ($gasLimit > 1_000_000) {
            throw new RuntimeException(
                'The local Hardhat gas limit exceeds the configured cap.'
            );
        }

        $gasPrice = $this->parseSepoliaRpcQuantity(
            $this->rpcForNetwork('hardhat-local', 'eth_gasPrice'),
            'local gas price'
        );

        if ($gasPrice <= 0) {
            throw new RuntimeException(
                'The local Hardhat RPC returned an invalid gas price.'
            );
        }

        $transaction = new Transaction([
            'nonce' => $this->sepoliaRpcQuantity($nonce),
            'from' => strtolower($signer),
            'to' => strtolower($contract),
            'gas' => $this->sepoliaRpcQuantity($gasLimit),
            'gasPrice' => $this->sepoliaRpcQuantity($gasPrice),
            'value' => '0x0',
            'chainId' => $chainId,
            'data' => $data,
        ]);

        $transaction->sign($privateKey);

        $recoveredSigner = strtolower($transaction->getFromAddress());

        if ($recoveredSigner !== strtolower($signer)) {
            throw new RuntimeException(
                'The signed local transaction does not match the configured signer.'
            );
        }

        $rawTransaction = $transaction->serialize();

        if (
            ! is_string($rawTransaction)
            || ! preg_match(
                '/^(?:0x)?(?:[a-fA-F0-9]{2})+$/',
                $rawTransaction
            )
        ) {
            throw new RuntimeException(
                'The local transaction serialization is invalid.'
            );
        }

        $rawBytes = hex2bin(
            str_starts_with(strtolower($rawTransaction), '0x')
                ? substr($rawTransaction, 2)
                : $rawTransaction
        );

        if ($rawBytes === false) {
            throw new RuntimeException(
                'The signed local transaction could not be decoded.'
            );
        }

        $util = new Util();
        $transactionHash = '0x' . strtolower($util->sha3($rawBytes));

        if (! preg_match('/^0x[a-f0-9]{64}$/', $transactionHash)) {
            throw new RuntimeException(
                'The locally calculated transaction hash is invalid.'
            );
        }

        return [
            'raw_transaction' => str_starts_with($rawTransaction, '0x')
                ? $rawTransaction
                : '0x' . $rawTransaction,
            'transaction_hash' => strtolower($transactionHash),
            'nonce' => $nonce,
            'gas_limit' => $gasLimit,
            'gas_price' => $gasPrice,
        ];
    }
    
    public function submitAnchorOnSepoliaAutomatically(
        BlockchainAnchor $anchor
    ): array {
        if (! config('blockchain.enabled', false)) {
            throw new RuntimeException(
                'Blockchain integration is disabled.'
            );
        }

        if (config('blockchain.network') !== 'sepolia') {
            throw new RuntimeException(
                'Automatic submission requires the configured network to be sepolia.'
            );
        }

        if (config('blockchain.sepolia.auto_submit') !== true) {
            throw new RuntimeException(
                'Automatic Sepolia submission is disabled.'
            );
        }

        if (! $anchor->exists || ! $anchor->getKey()) {
            throw new RuntimeException(
                'A persisted blockchain anchor is required.'
            );
        }

        $anchor = BlockchainAnchor::query()->findOrFail(
            $anchor->getKey()
        );

        if (
            $anchor->network !== 'sepolia'
            || (int) $anchor->chain_id !== 11155111
        ) {
            throw new RuntimeException(
                'The anchor must belong to Sepolia chain ID 11155111.'
            );
        }

        if (! in_array($anchor->status, [
            BlockchainAnchor::STATUS_PENDING,
            BlockchainAnchor::STATUS_FAILED,
        ], true) || ! empty($anchor->transaction_hash)) {
            throw new RuntimeException(
                'Only pending or failed Sepolia anchors without a transaction hash can be submitted.'
            );
        }

        return $this->submitAnchorOnSepoliaWithLock($anchor);
    }

    public function submitAnchorOnMainnetAutomatically(
        BlockchainAnchor $anchor
    ): array {
        if (
            ! config('blockchain.enabled', false)
            || config('blockchain.network') !== 'mainnet'
            || config('blockchain.mainnet.auto_submit') !== true
        ) {
            throw new RuntimeException(
                'Automatic Mainnet blockchain submission is disabled.'
            );
        }

        if (! $anchor->exists) {
            throw new RuntimeException(
                'A persisted blockchain anchor is required.'
            );
        }

        if (
            $anchor->network !== 'mainnet'
            || (int) $anchor->chain_id !== 1
        ) {
            throw new RuntimeException(
                'The anchor is not configured for Ethereum Mainnet.'
            );
        }

        if (! empty($anchor->transaction_hash)) {
            return [
                'anchor_id' => $anchor->id,
                'state' => 'reconciliation_required',
                'transaction_hash' => $anchor->transaction_hash,
                'message' => 'A transaction hash already exists. Reconciliation is required.',
            ];
        }

        if (! in_array($anchor->status, [
            BlockchainAnchor::STATUS_PENDING,
            BlockchainAnchor::STATUS_FAILED,
        ], true)) {
            throw new RuntimeException(
                'The anchor status does not permit automatic Mainnet submission.'
            );
        }

        return $this->submitAnchorOnMainnetWithLock($anchor);
    }

}