<?php

namespace Tests\Unit;

use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class BlockchainWriterAuthorizationTest extends TestCase
{
    private const CONTRACT =
        '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD';

    private const SIGNER =
        '0x181432526774881C0b66058A73E771D957f45193';

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

    public function test_returns_true_when_signer_is_authorized(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x' . str_repeat('0', 63) . '1',
            ]),
        ]);

        $service = new BlockchainProofService();

        $this->assertTrue($service->isWriter(self::SIGNER, 'mainnet'));

        Http::assertSent(function ($request) {
            $data = $request->data();

            return ($data['method'] ?? null) === 'eth_call'
                && ($data['params'][0]['to'] ?? null) === self::CONTRACT
                && ($data['params'][0]['data'] ?? null) ===
                    '0x8e7e80a2'
                    . str_pad(
                        strtolower(substr(self::SIGNER, 2)),
                        64,
                        '0',
                        STR_PAD_LEFT
                    );
        });
    }

    public function test_returns_false_when_signer_is_not_authorized(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x' . str_repeat('0', 64),
            ]),
        ]);

        $service = new BlockchainProofService();

        $this->assertFalse($service->isWriter(self::SIGNER, 'mainnet'));
    }

    public function test_rejects_malformed_writer_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x01',
            ]),
        ]);

        $service = new BlockchainProofService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The blockchain returned an invalid writer-permission response.'
        );

        $service->isWriter(self::SIGNER, 'mainnet');
    }

    public function test_rejects_invalid_signer_address(): void
    {
        $service = new BlockchainProofService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The blockchain signer address is invalid.'
        );

        $service->isWriter('not-an-address', 'mainnet');
    }
}