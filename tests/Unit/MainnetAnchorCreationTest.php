<?php

namespace Tests\Unit;

use App\Models\BlockchainAnchor;
use App\Models\DocumentFile;
use App\Models\DocumentPayment;
use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MainnetAnchorCreationTest extends TestCase
{
    private const CONTRACT_ADDRESS =
        '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'blockchain.enabled' => true,
            'blockchain.mainnet.chain_id' => 1,
            'blockchain.mainnet.contract_address' =>
                self::CONTRACT_ADDRESS,
            'blockchain.mainnet.auto_submit' => false,
        ]);

        Schema::create('blockchain_anchors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('document_file_id')->nullable();
            $table->unsignedBigInteger('document_payment_id')->nullable();
            $table->string('proof_type');
            $table->unsignedInteger('proof_version')->default(1);
            $table->string('proof_hash', 64);
            $table->string('network', 32);
            $table->unsignedBigInteger('chain_id');
            $table->string('contract_address', 42)->nullable();
            $table->string('transaction_hash', 66)->nullable();
            $table->uuid('submission_claim_token')->nullable();
            $table->timestamp('submission_claimed_at')->nullable();
            $table->unsignedBigInteger('block_number')->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['document_file_id', 'proof_version', 'network'],
                'test_document_version_network_unique'
            );

            $table->unique(
                ['document_payment_id', 'proof_version', 'network'],
                'test_payment_version_network_unique'
            );
        });
    }

    public function test_creates_pending_mainnet_document_anchor(): void
    {
        $file = new DocumentFile();
        $file->id = 42;

        $service = Mockery::mock(BlockchainProofService::class)
            ->makePartial();

        $service->shouldReceive('prepareDocumentProof')
            ->once()
            ->with($file)
            ->andReturn([
                'proof_type' => 'document',
                'proof_version' => 1,
                'document_file_id' => 42,
                'document_uuid' => 'test-document-uuid',
                'proof_hash' => str_repeat('a', 64),
            ]);

        $anchor = $service->createMainnetDocumentAnchor($file);

        $this->assertInstanceOf(BlockchainAnchor::class, $anchor);
        $this->assertNotEmpty($anchor->uuid);
        $this->assertSame(42, $anchor->document_file_id);
        $this->assertNull($anchor->document_payment_id);
        $this->assertSame(
            BlockchainAnchor::TYPE_DOCUMENT,
            $anchor->proof_type
        );
        $this->assertSame(str_repeat('a', 64), $anchor->proof_hash);
        $this->assertSame('mainnet', $anchor->network);
        $this->assertSame(1, $anchor->chain_id);
        $this->assertSame(
            self::CONTRACT_ADDRESS,
            $anchor->contract_address
        );
        $this->assertSame(
            BlockchainAnchor::STATUS_PENDING,
            $anchor->status
        );
        $this->assertSame(0, $anchor->attempts);
        $this->assertNull($anchor->transaction_hash);
        $this->assertDatabaseCount('blockchain_anchors', 1);

        Http::assertNothingSent();
    }

    public function test_creates_pending_mainnet_payment_anchor(): void
    {
        $payment = new DocumentPayment();
        $payment->id = 84;

        $service = Mockery::mock(BlockchainProofService::class)
            ->makePartial();

        $service->shouldReceive('preparePaymentProof')
            ->once()
            ->with($payment)
            ->andReturn([
                'proof_type' => 'payment',
                'proof_version' => 1,
                'payment_id' => 84,
                'proof_hash' => str_repeat('b', 64),
            ]);

        $anchor = $service->createMainnetPaymentAnchor($payment);

        $this->assertInstanceOf(BlockchainAnchor::class, $anchor);
        $this->assertNotEmpty($anchor->uuid);
        $this->assertNull($anchor->document_file_id);
        $this->assertSame(84, $anchor->document_payment_id);
        $this->assertSame(
            BlockchainAnchor::TYPE_PAYMENT,
            $anchor->proof_type
        );
        $this->assertSame(str_repeat('b', 64), $anchor->proof_hash);
        $this->assertSame('mainnet', $anchor->network);
        $this->assertSame(1, $anchor->chain_id);
        $this->assertSame(
            self::CONTRACT_ADDRESS,
            $anchor->contract_address
        );
        $this->assertSame(
            BlockchainAnchor::STATUS_PENDING,
            $anchor->status
        );
        $this->assertSame(0, $anchor->attempts);
        $this->assertNull($anchor->transaction_hash);
        $this->assertDatabaseCount('blockchain_anchors', 1);

        Http::assertNothingSent();
    }

    public function test_reuses_existing_matching_mainnet_payment_anchor(): void
    {
        $payment = new DocumentPayment();
        $payment->id = 84;

        $proofHash = str_repeat('b', 64);

        $existing = BlockchainAnchor::query()->create([
            'document_file_id' => null,
            'document_payment_id' => 84,
            'proof_type' => BlockchainAnchor::TYPE_PAYMENT,
            'proof_version' => 1,
            'proof_hash' => $proofHash,
            'network' => 'mainnet',
            'chain_id' => 1,
            'contract_address' => self::CONTRACT_ADDRESS,
            'status' => BlockchainAnchor::STATUS_PENDING,
            'attempts' => 0,
        ]);

        $service = Mockery::mock(BlockchainProofService::class)
            ->makePartial();

        $service->shouldReceive('preparePaymentProof')
            ->once()
            ->with($payment)
            ->andReturn([
                'proof_type' => 'payment',
                'proof_version' => 1,
                'payment_id' => 84,
                'proof_hash' => $proofHash,
            ]);

        $anchor = $service->createMainnetPaymentAnchor($payment);

        $this->assertSame($existing->id, $anchor->id);
        $this->assertSame(1, BlockchainAnchor::query()->count());

        Http::assertNothingSent();
    }

    public function test_rejects_existing_mainnet_payment_anchor_with_different_hash(): void
    {
        $payment = new DocumentPayment();
        $payment->id = 84;

        BlockchainAnchor::query()->create([
            'document_file_id' => null,
            'document_payment_id' => 84,
            'proof_type' => BlockchainAnchor::TYPE_PAYMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('c', 64),
            'network' => 'mainnet',
            'chain_id' => 1,
            'contract_address' => self::CONTRACT_ADDRESS,
            'status' => BlockchainAnchor::STATUS_PENDING,
            'attempts' => 0,
        ]);

        $service = Mockery::mock(BlockchainProofService::class)
            ->makePartial();

        $service->shouldReceive('preparePaymentProof')
            ->once()
            ->with($payment)
            ->andReturn([
                'proof_type' => 'payment',
                'proof_version' => 1,
                'payment_id' => 84,
                'proof_hash' => str_repeat('b', 64),
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'An existing Mainnet payment anchor has a different proof hash.'
        );

        $service->createMainnetPaymentAnchor($payment);
    }

    public function test_rejects_existing_mainnet_payment_anchor_with_wrong_chain_id(): void
    {
        $payment = new DocumentPayment();
        $payment->id = 84;

        BlockchainAnchor::query()->create([
            'document_file_id' => null,
            'document_payment_id' => 84,
            'proof_type' => BlockchainAnchor::TYPE_PAYMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('b', 64),
            'network' => 'mainnet',
            'chain_id' => 11155111,
            'contract_address' => self::CONTRACT_ADDRESS,
            'status' => BlockchainAnchor::STATUS_PENDING,
            'attempts' => 0,
        ]);

        $service = Mockery::mock(BlockchainProofService::class)
            ->makePartial();

        $service->shouldReceive('preparePaymentProof')
            ->once()
            ->with($payment)
            ->andReturn([
                'proof_type' => 'payment',
                'proof_version' => 1,
                'payment_id' => 84,
                'proof_hash' => str_repeat('b', 64),
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The existing Mainnet payment anchor uses different deployment metadata.'
        );

        $service->createMainnetPaymentAnchor($payment);
    }

    public function test_rejects_invalid_mainnet_chain_configuration(): void
    {
        config(['blockchain.mainnet.chain_id' => 11155111]);

        $payment = new DocumentPayment();
        $payment->id = 84;

        $service = Mockery::mock(BlockchainProofService::class)
            ->makePartial();

        $service->shouldReceive('preparePaymentProof')
            ->once()
            ->with($payment)
            ->andReturn([
                'proof_type' => 'payment',
                'proof_version' => 1,
                'payment_id' => 84,
                'proof_hash' => str_repeat('b', 64),
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The configured Ethereum Mainnet chain ID must be 1.'
        );

        $service->createMainnetPaymentAnchor($payment);
    }

    public function test_rejects_invalid_mainnet_contract_address(): void
    {
        config([
            'blockchain.mainnet.contract_address' => 'not-an-address',
        ]);

        $payment = new DocumentPayment();
        $payment->id = 84;

        $service = Mockery::mock(BlockchainProofService::class)
            ->makePartial();

        $service->shouldReceive('preparePaymentProof')
            ->once()
            ->with($payment)
            ->andReturn([
                'proof_type' => 'payment',
                'proof_version' => 1,
                'payment_id' => 84,
                'proof_hash' => str_repeat('b', 64),
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The configured Ethereum Mainnet contract address is invalid.'
        );

        $service->createMainnetPaymentAnchor($payment);
    }

    public function test_mainnet_document_anchor_creation_is_rejected_when_blockchain_is_disabled(): void
    {
        config(['blockchain.enabled' => false]);

        $file = new DocumentFile();
        $file->id = 42;

        $service = Mockery::mock(BlockchainProofService::class)
            ->makePartial();

        $service->shouldNotReceive('prepareDocumentProof');

        try {
            $service->createMainnetDocumentAnchor($file);
            $this->fail('Expected anchor creation to be rejected.');
        } catch (RuntimeException $e) {
            $this->assertSame(
                'Blockchain integration is disabled. Anchor creation is not allowed.',
                $e->getMessage()
            );
        }

        $this->assertDatabaseCount('blockchain_anchors', 0);

        Http::assertNothingSent();
    }
}
