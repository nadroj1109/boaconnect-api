<?php

namespace Tests\Unit;

use App\Enums\PaymentStatus;
use App\Jobs\CreateDocumentBlockchainAnchor;
use App\Jobs\CreatePaymentBlockchainAnchor;
use App\Models\BlockchainAnchor;
use App\Models\DocumentFile;
use App\Models\DocumentPayment;
use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MainnetBlockchainAnchorJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'blockchain.network' => 'mainnet',
            'blockchain.enabled' => true,
        ]);

        // Ensure this test uses its own in-memory SQLite connection.
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createTestSchema();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function createTestSchema(): void
    {
        Schema::create('document_files', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('document_payments', function (Blueprint $table) {
            $table->id();
            $table->string('status', 30);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_document_job_uses_mainnet_anchor_creation(): void
    {
        DB::table('document_files')->insert([
            'id' => 42,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $anchor = new BlockchainAnchor();
        $anchor->id = 100;
        $anchor->status = BlockchainAnchor::STATUS_PENDING;

        $called = false;

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldReceive('createMainnetDocumentAnchor')
            ->once()
            ->with(Mockery::on(
                fn ($file) =>
                    $file instanceof DocumentFile &&
                    $file->getKey() === 42
            ))
            ->andReturnUsing(function () use (&$called, $anchor) {
                $called = true;

                return $anchor;
            });

        (new CreateDocumentBlockchainAnchor(42))->handle($blockchain);

        $this->assertTrue($called);
    }

    public function test_payment_job_uses_mainnet_anchor_creation_for_paid_payment(): void
    {
        DB::table('document_payments')->insert([
            'id' => 84,
            'status' => PaymentStatus::PAID->value,
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $anchor = new BlockchainAnchor();
        $anchor->id = 101;
        $anchor->status = BlockchainAnchor::STATUS_PENDING;

        $called = false;

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldReceive('createMainnetPaymentAnchor')
            ->once()
            ->with(Mockery::on(
                fn ($payment) =>
                    $payment instanceof DocumentPayment &&
                    $payment->getKey() === 84 &&
                    $payment->isPaid() &&
                    $payment->paid_at !== null
            ))
            ->andReturnUsing(function () use (&$called, $anchor) {
                $called = true;

                return $anchor;
            });

        (new CreatePaymentBlockchainAnchor(84))->handle($blockchain);

        $this->assertTrue($called);
    }

    public function test_payment_job_rejects_unpaid_payment_before_anchor_creation(): void
    {
        DB::table('document_payments')->insert([
            'id' => 85,
            'status' => PaymentStatus::PENDING->value,
            'paid_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldNotReceive('createMainnetPaymentAnchor');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not confirmed as paid.');

        (new CreatePaymentBlockchainAnchor(85))->handle($blockchain);
    }

    public function test_document_job_returns_when_source_record_is_missing(): void
    {
        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldNotReceive('createMainnetDocumentAnchor');

        (new CreateDocumentBlockchainAnchor(999))->handle($blockchain);

        $this->assertDatabaseMissing('document_files', [
            'id' => 999,
        ]);
    }

    public function test_payment_job_returns_when_source_record_is_missing(): void
    {
        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldNotReceive('createMainnetPaymentAnchor');

        (new CreatePaymentBlockchainAnchor(999))->handle($blockchain);

        $this->assertDatabaseMissing('document_payments', [
            'id' => 999,
        ]);
    }

    public function test_document_anchor_job_returns_when_blockchain_is_disabled(): void
    {
        config(['blockchain.enabled' => false]);

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldNotReceive('createMainnetDocumentAnchor');

        (new CreateDocumentBlockchainAnchor(42))->handle($blockchain);

        $this->assertDatabaseMissing('document_files', [
            'id' => 42,
        ]);
    }

    public function test_payment_anchor_job_returns_when_blockchain_is_disabled(): void
    {
        config(['blockchain.enabled' => false]);

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldNotReceive('createMainnetPaymentAnchor');

        (new CreatePaymentBlockchainAnchor(84))->handle($blockchain);

        $this->assertDatabaseMissing('document_payments', [
            'id' => 84,
        ]);
    }
}
