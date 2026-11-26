<?php

namespace Tests\Unit;

use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class MainnetTransactionPreparationTest extends TestCase
{
    private const SIGNER =
        '0x181432526774881C0b66058A73E771D957f45193';

    private const CONTRACT =
        '0x1111111111111111111111111111111111111111';

    private BlockchainProofService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'blockchain.enabled' => true,
            'blockchain.network' => 'mainnet',
            'blockchain.mainnet.chain_id' => 1,
            'blockchain.mainnet.rpc_url' => 'https://mainnet-test.example',
            'blockchain.mainnet.contract_address' => self::CONTRACT,
            'blockchain.mainnet.signer_address' => self::SIGNER,
            'blockchain.mainnet.max_priority_fee_gwei' => 3,
            'blockchain.mainnet.max_base_fee_gwei' => 100,
            'blockchain.mainnet.max_total_fee_gwei' => 200,
        ]);

        $this->service = app(BlockchainProofService::class);
    }

    private function assertNoBroadcast(): void
    {
        Http::assertNotSent(function (Request $request): bool {
            $payload = json_decode($request->body(), true);

            return ($payload['method'] ?? null) === 'eth_sendRawTransaction';
        });
    }

    /**
     * Invoke the private transaction-preparation method without broadcasting.
     */
    private function prepare(
        ?string $signer = null,
        ?string $contract = null
    ): array {
        $method = new ReflectionMethod(
            BlockchainProofService::class,
            'prepareMainnetTransaction'
        );

        return $method->invoke(
            $this->service,
            $signer ?? self::SIGNER,
            $contract ?? self::CONTRACT,
            '0xc3e6a0dc' . str_repeat('0', 192)
        );
    }

    /**
     * Fake JSON-RPC responses by method name.
     *
     * Fee values passed in this helper are in gwei.
     */
    private function fakeMainnetRpc(
        int $priorityFeeGwei = 2,
        int $baseFeeGwei = 80,
        array $overrides = []
    ): void {
        $defaults = [
            'eth_chainId' => '0x1',
            'eth_getCode' => '0x6000',
            'eth_getTransactionCount' => '0x0',
            'eth_estimateGas' => '0x5208',
            'eth_maxPriorityFeePerGas' =>
                '0x' . dechex($priorityFeeGwei * 1_000_000_000),
            'eth_feeHistory' => [
                'baseFeePerGas' => [
                    '0x' . dechex($baseFeeGwei * 1_000_000_000),
                    '0x' . dechex($baseFeeGwei * 1_000_000_000),
                ],
            ],
        ];

        $responses = array_replace($defaults, $overrides);

        Http::fake(function (Request $request) use ($responses) {
            $payload = json_decode($request->body(), true);

            $rpcMethod = $payload['method'] ?? '';

            if (! array_key_exists($rpcMethod, $responses)) {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => $payload['id'] ?? 1,
                    'error' => [
                        'code' => -32601,
                        'message' => 'Method not mocked: ' . $rpcMethod,
                    ],
                ], 200);
            }

            return Http::response([
                'jsonrpc' => '2.0',
                'id' => $payload['id'] ?? 1,
                'result' => $responses[$rpcMethod],
            ], 200);
        });
    }

    public function test_prepares_a_mainnet_eip1559_transaction(): void
    {
        $this->fakeMainnetRpc(
            priorityFeeGwei: 2,
            baseFeeGwei: 80
        );

        $transaction = $this->prepare();

        $this->assertSame('0x1', $transaction['chainId']);
        $this->assertSame('0x0', $transaction['nonce']);
        $this->assertSame('0x6270', $transaction['gas']);

        $this->assertSame(
            '0x' . dechex(2_000_000_000),
            $transaction['maxPriorityFeePerGas']
        );

        // 2 × 80 gwei base fee + 2 gwei priority fee = 162 gwei.
        $this->assertSame(
            '0x' . dechex(162_000_000_000),
            $transaction['maxFeePerGas']
        );

        $this->assertSame(
            strtolower(self::CONTRACT),
            $transaction['to']
        );

        $this->assertSame('0x0', $transaction['value']);
        $this->assertSame([], $transaction['accessList']);

        $this->assertNoBroadcast();
    }

    public function test_rejects_priority_fee_above_configured_cap(): void
    {
        $this->fakeMainnetRpc(
            priorityFeeGwei: 4,
            baseFeeGwei: 80
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The Mainnet priority fee is outside the configured cap.'
        );

        try {
            $this->prepare();
        } finally {
            $this->assertNoBroadcast();
        }
    }

    public function test_rejects_base_fee_above_configured_cap(): void
    {
        $this->fakeMainnetRpc(
            priorityFeeGwei: 2,
            baseFeeGwei: 101
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The Mainnet base fee is outside the configured cap.'
        );

        try {
            $this->prepare();
        } finally {
            $this->assertNoBroadcast();
        }
    }

    public function test_rejects_calculated_maximum_fee_above_cap(): void
    {
        // 2 × 100 + 3 = 203 gwei, exceeding the 200 gwei cap.
        $this->fakeMainnetRpc(
            priorityFeeGwei: 3,
            baseFeeGwei: 100
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The calculated Mainnet maximum fee exceeds the configured cap.'
        );

        try {
            $this->prepare();
        } finally {
            $this->assertNoBroadcast();
        }
    }

    public function test_rejects_invalid_fee_history(): void
    {
        $this->fakeMainnetRpc(
            overrides: [
                'eth_feeHistory' => [
                    'baseFeePerGas' => [],
                ],
            ]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The Mainnet RPC returned invalid fee history.'
        );

        try {
            $this->prepare();
        } finally {
            $this->assertNoBroadcast();
        }
    }

    public function test_rejects_signer_that_differs_from_configuration(): void
    {
        $this->fakeMainnetRpc();

        $wrongSigner =
            '0x2222222222222222222222222222222222222222';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The Mainnet transaction signer does not match configuration.'
        );

        try {
            $this->prepare(signer: $wrongSigner);
        } finally {
            $this->assertNoBroadcast();
        }
    }

    public function test_rejects_contract_that_differs_from_configuration(): void
    {
        $this->fakeMainnetRpc();

        $wrongContract =
            '0x3333333333333333333333333333333333333333';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The Mainnet transaction contract does not match configuration.'
        );

        try {
            $this->prepare(contract: $wrongContract);
        } finally {
            $this->assertNoBroadcast();
        }
    }

    public function test_rejects_rpc_connected_to_the_wrong_chain(): void
    {
        $this->fakeMainnetRpc(
            overrides: [
                'eth_chainId' => '0xaa36a7',
            ]
        );

        $this->expectException(RuntimeException::class);

        try {
            $this->prepare();
        } finally {
            $this->assertNoBroadcast();
        }
    }
}
