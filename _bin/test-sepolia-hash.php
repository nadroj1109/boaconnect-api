<?php

require __DIR__ . '/vendor/autoload.php';

$util = new \Web3p\EthereumUtil\Util();
$known = $util->sha3('abc');
$known = strtolower(preg_replace('/^0x/i', '', (string) $known));

echo json_encode([
    'known_keccak256_test_passed' =>
        $known === '4e03657aea45a94fc7d47ba826c8d667c0d1e6e33a64a036ec44f58fa12d6c45',
    'hash_output_is_64_hex_chars' =>
        strlen($known) === 64 && ctype_xdigit($known),
], JSON_PRETTY_PRINT) . PHP_EOL;
