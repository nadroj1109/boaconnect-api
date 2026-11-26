<?php

return [
    'enabled' => env('BLOCKCHAIN_ENABLED', false),
    'network' => env('BLOCKCHAIN_NETWORK', 'hardhat-local'),
    'rpc_url' => env(
        'BLOCKCHAIN_RPC_URL',
        'http://127.0.0.1:8545'
    ),
    'chain_id' => (int) env('BLOCKCHAIN_CHAIN_ID', 31337),
    'contract_address' => env('BLOCKCHAIN_CONTRACT_ADDRESS'),
    'signer_address' => env('BLOCKCHAIN_SIGNER_ADDRESS'),

    'hardhat_local' => [
        'chain_id' => 31337,
        'rpc_url' => env('BLOCKCHAIN_LOCAL_RPC_URL', 'http://127.0.0.1:8545'),
        'contract_address' => env('BLOCKCHAIN_LOCAL_CONTRACT_ADDRESS'),
        'signer_address' => env('BLOCKCHAIN_LOCAL_SIGNER_ADDRESS'),
        'private_key' => env('BLOCKCHAIN_LOCAL_PRIVATE_KEY'),
    ],

    'sepolia' => [
        'chain_id' => 11155111,
        'rpc_url' => env('BLOCKCHAIN_SEPOLIA_RPC_URL'),
        'contract_address' => env('BLOCKCHAIN_SEPOLIA_CONTRACT_ADDRESS'),
        'signer_address' => env('BLOCKCHAIN_SEPOLIA_SIGNER_ADDRESS'),
        'private_key' => env('BLOCKCHAIN_SEPOLIA_PRIVATE_KEY'),
        'auto_submit' => env('BLOCKCHAIN_SEPOLIA_AUTO_SUBMIT', false),
    ],

    'mainnet' => [
        'chain_id' => 1,
        'rpc_url' => env('BLOCKCHAIN_MAINNET_RPC_URL'),
        'contract_address' => env('BLOCKCHAIN_MAINNET_CONTRACT_ADDRESS'),
        'signer_address' => env('BLOCKCHAIN_MAINNET_SIGNER_ADDRESS'),
        'private_key' => env('BLOCKCHAIN_MAINNET_PRIVATE_KEY'),
        'auto_submit' => env('BLOCKCHAIN_MAINNET_AUTO_SUBMIT', false),
        // Maximum allowed EIP-1559 fees, in gwei.
        'max_priority_fee_gwei' => (int) env(
            'BLOCKCHAIN_MAINNET_MAX_PRIORITY_FEE_GWEI',
            3
        ),
        'max_base_fee_gwei' => (int) env(
            'BLOCKCHAIN_MAINNET_MAX_BASE_FEE_GWEI',
            100
        ),
        'max_total_fee_gwei' => (int) env(
            'BLOCKCHAIN_MAINNET_MAX_TOTAL_FEE_GWEI',
            200
        ),
    ],

    'polygon' => [
        'chain_id' => 137,
        'rpc_url' => env('BLOCKCHAIN_POLYGON_RPC_URL'),
        'contract_address' => env('BLOCKCHAIN_POLYGON_CONTRACT_ADDRESS'),
        'signer_address' => env('BLOCKCHAIN_POLYGON_SIGNER_ADDRESS'),
        'private_key' => env('BLOCKCHAIN_POLYGON_PRIVATE_KEY'),
        'auto_submit' => env('BLOCKCHAIN_POLYGON_AUTO_SUBMIT', false),

        'max_priority_fee_gwei' => (int) env(
            'BLOCKCHAIN_POLYGON_MAX_PRIORITY_FEE_GWEI',
            50
        ),
        'max_base_fee_gwei' => (int) env(
            'BLOCKCHAIN_POLYGON_MAX_BASE_FEE_GWEI',
            500
        ),
        'max_total_fee_gwei' => (int) env(
            'BLOCKCHAIN_POLYGON_MAX_TOTAL_FEE_GWEI',
            600
        ),
    ],

];
