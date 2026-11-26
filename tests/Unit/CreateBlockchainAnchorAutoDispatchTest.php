<?php

namespace Tests\Unit;

use App\Enums\PaymentStatus;
use App\Jobs\AutoSubmitSepoliaBlockchainAnchor;
use App\Jobs\AutoSubmitMainnetBlockchainAnchor;
use App\Jobs\CreateDocumentBlockchainAnchor;
use App\Jobs\CreatePaymentBlockchainAnchor;
use App\Models\BlockchainAnchor;
use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CreateBlockchainAnchorAutoDispatchTest extends TestCase
{
    private const CONTRACT =
        '0xB4e0bad25a9F813f2b5bfA2628754F10a42833CD';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'blockchain.enabled' => true,
            'blockchain.network' => 'sepolia',
            'blockchain.sepolia.auto_submit' => true,
            'blockchain.sepolia.chain_id' => 11155111,
            'blockchain.sepolia.contract_address' => self::CONTRACT,
        ]);

        Schema::create('document_files', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('document_payments', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

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

        Queue::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        Schema::dropIfExists('blockchain_anchors');
        Schema::dropIfExists('document_payments');
        Schema::dropIfExists('document_files');

        parent::tearDown();
    }

    private function createAnchor(
        string $network = 'sepolia',
        string $status = BlockchainAnchor::STATUS_PENDING,
        ?string $transactionHash = null,
        ?int $documentFileId = null,
        ?int $documentPaymentId = null,
    ): BlockchainAnchor {
        return BlockchainAnchor::query()->create([
            'uuid' => (string) Str::uuid(),
            'document_file_id' => $documentFileId,
            'document_payment_id' => $documentPaymentId,
            'proof_type' => $documentFileId !== null
                ? BlockchainAnchor::TYPE_DOCUMENT
                : BlockchainAnchor::TYPE_PAYMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('a', 64),
            'network' => $network,
            'chain_id' => $network === 'mainnet' ? 1 : 11155111,
            'contract_address' => self::CONTRACT,
            'transaction_hash' => $transactionHash,
            'status' => $status,
            'attempts' => 0,
        ]);
    }

    public function test_document_anchor_creation_dispatches_auto_submission(): void
    {
        DB::table('document_files')->insert([
            'id' => 42,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $anchor = $this->createAnchor(
            documentFileId: 42
        );

        $service = Mockery::mock(BlockchainProofService::class);

        $service->shouldReceive('createSepoliaDocumentAnchor')
            ->once()
            ->andReturn($anchor);

        (new CreateDocumentBlockchainAnchor(42))->handle($service);

        Queue::assertPushed(
            AutoSubmitSepoliaBlockchainAnchor::class,
            fn ($job) => $job->anchorId === $anchor->id
        );
    }

    public function test_paid_payment_anchor_creation_dispatches_auto_submission(): void
    {
        DB::table('document_payments')->insert([
            'id' => 84,
            'status' => PaymentStatus::PAID->value,
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $anchor = $this->createAnchor(
            documentPaymentId: 84
        );

        $service = Mockery::mock(BlockchainProofService::class);

        $service->shouldReceive('createSepoliaPaymentAnchor')
            ->once()
            ->andReturn($anchor);

        (new CreatePaymentBlockchainAnchor(84))->handle($service);

        Queue::assertPushed(
            AutoSubmitSepoliaBlockchainAnchor::class,
            fn ($job) => $job->anchorId === $anchor->id
        );
    }

    public function test_document_anchor_does_not_dispatch_when_auto_submission_is_disabled(): void
    {
        config(['blockchain.sepolia.auto_submit' => false]);

        DB::table('document_files')->insert([
            'id' => 42,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $anchor = $this->createAnchor(documentFileId: 42);

        $service = Mockery::mock(BlockchainProofService::class);

        $service->shouldReceive('createSepoliaDocumentAnchor')
            ->once()
            ->andReturn($anchor);

        (new CreateDocumentBlockchainAnchor(42))->handle($service);

        Queue::assertNotPushed(AutoSubmitSepoliaBlockchainAnchor::class);
    }

    public function test_mainnet_document_anchor_never_dispatches_sepolia_submission(): void
    {
        config([
            'blockchain.network' => 'mainnet',
            'blockchain.sepolia.auto_submit' => true,
        ]);

        DB::table('document_files')->insert([
            'id' => 42,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $anchor = $this->createAnchor(
            network: 'mainnet',
            documentFileId: 42
        );

        $service = Mockery::mock(BlockchainProofService::class);

        $service->shouldReceive('createMainnetDocumentAnchor')
            ->once()
            ->andReturn($anchor);

        (new CreateDocumentBlockchainAnchor(42))->handle($service);

        Queue::assertNotPushed(AutoSubmitSepoliaBlockchainAnchor::class);
    }

    public function test_confirmed_document_anchor_does_not_dispatch_submission(): void
    {
        DB::table('document_files')->insert([
            'id' => 42,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $anchor = $this->createAnchor(
            status: BlockchainAnchor::STATUS_CONFIRMED,
            documentFileId: 42
        );

        $service = Mockery::mock(BlockchainProofService::class);

        $service->shouldReceive('createSepoliaDocumentAnchor')
            ->once()
            ->andReturn($anchor);

        (new CreateDocumentBlockchainAnchor(42))->handle($service);

        Queue::assertNotPushed(AutoSubmitSepoliaBlockchainAnchor::class);
    }

    public function test_unpaid_payment_is_not_anchored_or_submitted(): void
    {
        DB::table('document_payments')->insert([
            'id' => 84,
            'status' => PaymentStatus::PENDING->value,
            'paid_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = Mockery::mock(BlockchainProofService::class);

        $service->shouldNotReceive('createSepoliaPaymentAnchor');

        try {
            (new CreatePaymentBlockchainAnchor(84))->handle($service);
            $this->fail('Expected unpaid payment to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Payment 84 is not confirmed as paid.',
                $exception->getMessage()
            );
        }

        Queue::assertNotPushed(AutoSubmitSepoliaBlockchainAnchor::class);
    }

    public function test_mainnet_document_anchor_dispatches_mainnet_submission_when_enabled(): void
    {
        config([
            'blockchain.network' => 'mainnet',
            'blockchain.mainnet.auto_submit' => true,
        ]);

        DB::table('document_files')->insert([
            'id' => 42,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $anchor = $this->createAnchor(
            network: 'mainnet',
            documentFileId: 42
        );

        $service = Mockery::mock(BlockchainProofService::class);

        $service->shouldReceive('createMainnetDocumentAnchor')
            ->once()
            ->andReturn($anchor);

        (new CreateDocumentBlockchainAnchor(42))->handle($service);

        Queue::assertPushed(
            AutoSubmitMainnetBlockchainAnchor::class,
            fn ($job) => $job->anchorId === $anchor->id
        );

        Queue::assertNotPushed(AutoSubmitSepoliaBlockchainAnchor::class);
    }

    public function test_mainnet_payment_anchor_dispatches_mainnet_submission_when_enabled(): void
    {
        config([
            'blockchain.network' => 'mainnet',
            'blockchain.mainnet.auto_submit' => true,
        ]);

        DB::table('document_payments')->insert([
            'id' => 84,
            'status' => PaymentStatus::PAID->value,
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $anchor = $this->createAnchor(
            network: 'mainnet',
            documentPaymentId: 84
        );

        $service = Mockery::mock(BlockchainProofService::class);

        $service->shouldReceive('createMainnetPaymentAnchor')
            ->once()
            ->andReturn($anchor);

        (new CreatePaymentBlockchainAnchor(84))->handle($service);

        Queue::assertPushed(
            AutoSubmitMainnetBlockchainAnchor::class,
            fn ($job) => $job->anchorId === $anchor->id
        );

        Queue::assertNotPushed(AutoSubmitSepoliaBlockchainAnchor::class);
    }

    public function test_mainnet_document_anchor_does_not_dispatch_when_auto_submission_is_disabled(): void
    {
        config([
            'blockchain.network' => 'mainnet',
            'blockchain.mainnet.auto_submit' => false,
        ]);

        DB::table('document_files')->insert([
            'id' => 42,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $anchor = $this->createAnchor(
            network: 'mainnet',
            documentFileId: 42
        );

        $service = Mockery::mock(BlockchainProofService::class);

        $service->shouldReceive('createMainnetDocumentAnchor')
            ->once()
            ->andReturn($anchor);

        (new CreateDocumentBlockchainAnchor(42))->handle($service);

        Queue::assertNotPushed(AutoSubmitMainnetBlockchainAnchor::class);
        Queue::assertNotPushed(AutoSubmitSepoliaBlockchainAnchor::class);
    }

    public function test_mainnet_payment_anchor_does_not_dispatch_when_auto_submission_is_disabled(): void
    {
        config([
            'blockchain.network' => 'mainnet',
            'blockchain.mainnet.auto_submit' => false,
        ]);

        DB::table('document_payments')->insert([
            'id' => 84,
            'status' => PaymentStatus::PAID->value,
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $anchor = $this->createAnchor(
            network: 'mainnet',
            documentPaymentId: 84
        );

        $service = Mockery::mock(BlockchainProofService::class);

        $service->shouldReceive('createMainnetPaymentAnchor')
            ->once()
            ->andReturn($anchor);

        (new CreatePaymentBlockchainAnchor(84))->handle($service);

        Queue::assertNotPushed(AutoSubmitMainnetBlockchainAnchor::class);
        Queue::assertNotPushed(AutoSubmitSepoliaBlockchainAnchor::class);
    }

}
