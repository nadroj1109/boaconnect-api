<?php

namespace Tests\Unit;

use App\Enums\DocumentRequestStatus;
use App\Services\Blockchain\BlockchainProofService;
use App\Services\DocumentRequest\DocumentVerificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class DocumentVerificationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('barangays', function (Blueprint $table) {
            $table->id();
            $table->string('barangay');
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no');
            $table->unsignedBigInteger('barangay_id');
            $table->unsignedBigInteger('document_type_id');
            $table->string('status', 30);
            $table->timestamp('released_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('document_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('document_request_id');
            $table->string('disk');
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('blockchain_anchors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('document_file_id')->nullable();
            $table->unsignedBigInteger('document_payment_id')->nullable();
            $table->string('proof_type');
            $table->unsignedInteger('proof_version');
            $table->string('proof_hash', 64);
            $table->string('status');
            $table->timestamps();
        });

    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_unreleased_document_does_not_trigger_blockchain_verification(): void
    {
        DB::table('barangays')->insert([
            'id' => 1,
            'barangay' => 'Test Barangay',
        ]);

        DB::table('document_types')->insert([
            'id' => 1,
            'name' => 'Barangay Clearance',
        ]);

        DB::table('document_requests')->insert([
            'id' => 1,
            'request_no' => 'DR-2026-000001',
            'barangay_id' => 1,
            'document_type_id' => 1,
            'status' => DocumentRequestStatus::PENDING->value,
            'released_at' => null,
            'deleted_at' => null,
        ]);

        $uuid = (string) Str::uuid();

        DB::table('document_files')->insert([
            'uuid' => $uuid,
            'document_request_id' => 1,
            'disk' => 'local',
            'path' => 'test/document.pdf',
            'filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'checksum' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $blockchain = Mockery::mock(BlockchainProofService::class);
        $blockchain->shouldNotReceive('prepareDocumentProof');
        $blockchain->shouldNotReceive('inspectAnchorOnChain');

        $service = new DocumentVerificationService($blockchain);

        $result = $service->verify($uuid);

        $this->assertFalse($result['valid']);
        $this->assertSame('NOT_RELEASED', $result['status']);
        $this->assertNull($result['document']);
        $this->assertArrayNotHasKey('blockchain', $result);
    }

    public function test_released_document_without_confirmed_anchor_remains_valid_but_not_anchored(): void
    {
        DB::table('barangays')->insert([
            'id' => 1,
            'barangay' => 'Test Barangay',
        ]);

        DB::table('document_types')->insert([
            'id' => 1,
            'name' => 'Barangay Clearance',
            'deleted_at' => null,
        ]);

        DB::table('document_requests')->insert([
            'id' => 1,
            'request_no' => 'DR-2026-000002',
            'barangay_id' => 1,
            'document_type_id' => 1,
            'status' => DocumentRequestStatus::RELEASED->value,
            'released_at' => now(),
            'deleted_at' => null,
        ]);

        $uuid = (string) Str::uuid();

        DB::table('document_files')->insert([
            'uuid' => $uuid,
            'document_request_id' => 1,
            'disk' => 'local',
            'path' => 'test/document.pdf',
            'filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'checksum' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $blockchain = Mockery::mock(BlockchainProofService::class);
        $blockchain->shouldReceive('prepareDocumentProof')
            ->once()
            ->andReturn([
                'proof_type' => 'document',
                'proof_version' => 1,
                'document_file_id' => 1,
                'document_uuid' => $uuid,
                'proof_hash' => str_repeat('a', 64),
            ]);

        $blockchain->shouldNotReceive('inspectAnchorOnChain');

        $service = new DocumentVerificationService($blockchain);

        $result = $service->verify($uuid);

        $this->assertTrue($result['valid']);
        $this->assertSame('VALID', $result['status']);
        $this->assertSame('Test Barangay', $result['document']['barangay']);
        $this->assertSame('not_anchored', $result['blockchain']['status']);
        $this->assertFalse($result['blockchain']['verified']);
    }

    public function test_confirmed_anchor_with_mismatched_hash_is_not_verified(): void
    {
        DB::table('barangays')->insert([
            'id' => 1,
            'barangay' => 'Test Barangay',
        ]);

        DB::table('document_types')->insert([
            'id' => 1,
            'name' => 'Barangay Clearance',
            'deleted_at' => null,
        ]);

        DB::table('document_requests')->insert([
            'id' => 1,
            'request_no' => 'DR-2026-000003',
            'barangay_id' => 1,
            'document_type_id' => 1,
            'status' => DocumentRequestStatus::RELEASED->value,
            'released_at' => now(),
            'deleted_at' => null,
        ]);

        $uuid = (string) Str::uuid();

        DB::table('document_files')->insert([
            'id' => 1,
            'uuid' => $uuid,
            'document_request_id' => 1,
            'disk' => 'local',
            'path' => 'test/document.pdf',
            'filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'checksum' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('blockchain_anchors')->insert([
            'uuid' => (string) Str::uuid(),
            'document_file_id' => 1,
            'document_payment_id' => null,
            'proof_type' => \App\Models\BlockchainAnchor::TYPE_DOCUMENT,
            'proof_version' => 1,
            'proof_hash' => str_repeat('b', 64),
            'status' => \App\Models\BlockchainAnchor::STATUS_CONFIRMED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $blockchain = Mockery::mock(BlockchainProofService::class);
        $blockchain->shouldReceive('prepareDocumentProof')
            ->once()
            ->andReturn([
                'proof_type' => 'document',
                'proof_version' => 1,
                'document_file_id' => 1,
                'document_uuid' => $uuid,
                'proof_hash' => str_repeat('a', 64),
            ]);

        $blockchain->shouldNotReceive('inspectAnchorOnChain');

        $service = new DocumentVerificationService($blockchain);

        $result = $service->verify($uuid);

        $this->assertTrue($result['valid']);
        $this->assertSame('VALID', $result['status']);
        $this->assertSame('mismatch', $result['blockchain']['status']);
        $this->assertFalse($result['blockchain']['verified']);
    }

    public function test_released_document_with_matching_confirmed_anchor_is_verified(): void
    {
        DB::table('barangays')->insert([
            'id' => 1,
            'barangay' => 'Test Barangay',
        ]);

        DB::table('document_types')->insert([
            'id' => 1,
            'name' => 'Barangay Clearance',
            'deleted_at' => null,
        ]);

        DB::table('document_requests')->insert([
            'id' => 1,
            'request_no' => 'DR-2026-000004',
            'barangay_id' => 1,
            'document_type_id' => 1,
            'status' => DocumentRequestStatus::RELEASED->value,
            'released_at' => now(),
            'deleted_at' => null,
        ]);

        $uuid = (string) Str::uuid();
        $proofHash = str_repeat('a', 64);

        DB::table('document_files')->insert([
            'id' => 1,
            'uuid' => $uuid,
            'document_request_id' => 1,
            'disk' => 'local',
            'path' => 'test/document.pdf',
            'filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'checksum' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('blockchain_anchors')->insert([
            'uuid' => (string) Str::uuid(),
            'document_file_id' => 1,
            'document_payment_id' => null,
            'proof_type' => \App\Models\BlockchainAnchor::TYPE_DOCUMENT,
            'proof_version' => 1,
            'proof_hash' => $proofHash,
            'status' => \App\Models\BlockchainAnchor::STATUS_CONFIRMED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldReceive('prepareDocumentProof')
            ->once()
            ->andReturn([
                'proof_type' => 'document',
                'proof_version' => 1,
                'document_file_id' => 1,
                'document_uuid' => $uuid,
                'proof_hash' => $proofHash,
            ]);

        $blockchain->shouldReceive('inspectAnchorOnChain')
            ->once()
            ->with(Mockery::on(fn ($anchor) =>
                $anchor instanceof \App\Models\BlockchainAnchor
                && $anchor->status === \App\Models\BlockchainAnchor::STATUS_CONFIRMED
                && $anchor->proof_hash === $proofHash
            ))
            ->andReturn([
                'state' => 'matching',
                'anchor_id' => 1,
                'proof_id' => '0x' . str_repeat('c', 64),
                'on_chain_proof' => [
                    'exists' => true,
                    'proof_type' => 1,
                    'proof_hash' => '0x' . $proofHash,
                ],
            ]);

        $service = new DocumentVerificationService($blockchain);

        $result = $service->verify($uuid);

        $this->assertTrue($result['valid']);
        $this->assertSame('VALID', $result['status']);
        $this->assertSame('verified', $result['blockchain']['status']);
        $this->assertTrue($result['blockchain']['verified']);
    }

    public function test_blockchain_inspection_failure_does_not_invalidate_released_document(): void
    {
        DB::table('barangays')->insert([
            'id' => 1,
            'barangay' => 'Test Barangay',
        ]);

        DB::table('document_types')->insert([
            'id' => 1,
            'name' => 'Barangay Clearance',
            'deleted_at' => null,
        ]);

        DB::table('document_requests')->insert([
            'id' => 1,
            'request_no' => 'DR-2026-000005',
            'barangay_id' => 1,
            'document_type_id' => 1,
            'status' => DocumentRequestStatus::RELEASED->value,
            'released_at' => now(),
            'deleted_at' => null,
        ]);

        $uuid = (string) Str::uuid();
        $proofHash = str_repeat('a', 64);

        DB::table('document_files')->insert([
            'id' => 1,
            'uuid' => $uuid,
            'document_request_id' => 1,
            'disk' => 'local',
            'path' => 'test/document.pdf',
            'filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'checksum' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('blockchain_anchors')->insert([
            'uuid' => (string) Str::uuid(),
            'document_file_id' => 1,
            'document_payment_id' => null,
            'proof_type' => \App\Models\BlockchainAnchor::TYPE_DOCUMENT,
            'proof_version' => 1,
            'proof_hash' => $proofHash,
            'status' => \App\Models\BlockchainAnchor::STATUS_CONFIRMED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $blockchain = Mockery::mock(BlockchainProofService::class);

        $blockchain->shouldReceive('prepareDocumentProof')
            ->once()
            ->andReturn([
                'proof_type' => 'document',
                'proof_version' => 1,
                'document_file_id' => 1,
                'document_uuid' => $uuid,
                'proof_hash' => $proofHash,
            ]);

        $blockchain->shouldReceive('inspectAnchorOnChain')
            ->once()
            ->andThrow(new \RuntimeException('Simulated blockchain outage'));

        $service = new DocumentVerificationService($blockchain);

        $result = $service->verify($uuid);

        $this->assertTrue($result['valid']);
        $this->assertSame('VALID', $result['status']);
        $this->assertSame('unavailable', $result['blockchain']['status']);
        $this->assertFalse($result['blockchain']['verified']);
        $this->assertSame(
            'Blockchain verification is temporarily unavailable.',
            $result['blockchain']['message']
        );
    }

}
