<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$privateKey = (string) config('blockchain.sepolia.private_key');
$privateKey = preg_replace('/^0x/i', '', $privateKey);
$signer = strtolower((string) config('blockchain.sepolia.signer_address'));

$tx = new \Web3p\EthereumTx\EIP1559Transaction([
    'chainId' => '0xaa36a7',
    'nonce' => '0x2',
    'maxPriorityFeePerGas' => '0x3b9aca00',
    'maxFeePerGas' => '0xb2d05e00',
    'gas' => '0x6270',
    'to' => '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD',
    'value' => '0x0',
    'data' => '0xc3e6a0dc' . str_repeat('0', 192),
    'accessList' => [],
]);

$raw = $tx->sign('0x' . $privateKey);
$recovered = strtolower($tx->getFromAddress());

echo json_encode([
    'valid_type2_hex' => is_string($raw)
        && (bool) preg_match('/^02(?:[a-fA-F0-9]{2})+$/', $raw),
    'signer_matches' => $signer !== ''
        && hash_equals($signer, $recovered),
], JSON_PRETTY_PRINT) . PHP_EOL;
