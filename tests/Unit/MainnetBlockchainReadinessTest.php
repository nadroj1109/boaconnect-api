<?php

namespace Tests\Unit;

use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class TestableBlockchainProofService extends BlockchainProofService
{
    public function assertReadyForTest(string $network): void
    {
        $this->assertReady($network);
    }
}

class MainnetBlockchainReadinessTest extends TestCase
{
    private const CONTRACT =
        '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'blockchain.enabled' => true,
            'blockchain.mainnet.chain_id' => 1,
            'blockchain.mainnet.rpc_url' => 'https://mainnet-test.example',
            'blockchain.mainnet.contract_address' => self::CONTRACT,
        ]);
    }

    public function test_reads_mainnet_chain_id(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x1',
            ]),
        ]);

        $service = new BlockchainProofService();

        $this->assertSame(1, $service->getChainId('mainnet'));

        Http::assertSent(fn ($request) =>
            ($request->data()['method'] ?? null) === 'eth_chainId'
        );
    }
  
    public function test_rejects_wrong_mainnet_chain_id(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0xaa36a7',
            ]),
        ]);

        $service = new TestableBlockchainProofService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Wrong blockchain network. Expected chain ID 1, got 11155111.'
        );

        $service->assertReadyForTest('mainnet');
    }

    public function test_rejects_malformed_mainnet_chain_id(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0xgg',
            ]),
        ]);

        $service = new BlockchainProofService();

        $this->expectException(RuntimeException::class);

        $service->getChainId('mainnet');
    }

    public function test_reports_mainnet_contract_without_bytecode_as_not_deployed(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x',
            ]),
        ]);

        $service = new BlockchainProofService();

        $this->assertFalse(
            $service->isContractDeployed('mainnet')
        );
    }

    public function test_reports_mainnet_contract_with_bytecode_as_deployed(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x60016000',
            ]),
        ]);

        $service = new BlockchainProofService();

        $this->assertTrue(
            $service->isContractDeployed('mainnet')
        );
    }

    public function test_rejects_mainnet_rpc_access_when_blockchain_is_disabled(): void
    {
        config(['blockchain.enabled' => false]);

        Http::fake();

        $service = new BlockchainProofService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Blockchain integration is disabled.'
        );

        $service->getChainId('mainnet');
    }
}