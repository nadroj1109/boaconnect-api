<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$privateKey = (string) config('blockchain.sepolia.private_key');
if (str_starts_with(strtolower($privateKey), '0x')) {
    $privateKey = substr($privateKey, 2);
}

$signer = strtolower((string) config('blockchain.sepolia.signer_address'));

if (strlen($privateKey) !== 64 || !ctype_xdigit($privateKey)) {
    throw new RuntimeException('Sepolia private key configuration is invalid.');
}

$transaction = new \Web3p\EthereumTx\EIP1559Transaction([
    'chainId' => 11155111,
    'nonce' => '0x01',
    'maxPriorityFeePerGas' => '0x3b9aca00',
    'maxFeePerGas' => '0x77359400',
    'gas' => '0x186a0',
    'to' => config('blockchain.sepolia.contract_address'),
    'value' => '0x0',
    'data' => '0xc3e6a0dc' . str_repeat('0', 192),
    'accessList' => [],
]);

$raw = $transaction->sign('0x' . $privateKey);
$recovered = strtolower($transaction->getFromAddress());

echo json_encode([
    'signed_raw_transaction_format_valid' =>
        str_starts_with(strtolower($raw), '02')
        && ctype_xdigit($raw)
        && strlen($raw) % 2 === 0,
    'signed_transaction_length_bytes' => (int) (strlen($raw) / 2),
    'signer_matches_configuration' =>
        $signer !== '' && hash_equals($signer, $recovered),
], JSON_PRETTY_PRINT) . PHP_EOL;
