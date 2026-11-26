<?php

namespace Tests\Feature;

use App\Services\DocumentRequest\DocumentVerificationService;
use Tests\TestCase;

class DocumentVerificationEndpointTest extends TestCase
{
    public function test_unknown_document_returns_existing_api_response_envelope(): void
    {
        $uuid = '11111111-1111-4111-8111-111111111111';

        $this->mock(DocumentVerificationService::class)
            ->shouldReceive('verify')
            ->once()
            ->with($uuid)
            ->andReturn([
                'valid' => false,
                'status' => 'NOT_FOUND',
                'message' => 'The document could not be found.',
                'document' => null,
            ]);

        $response = $this->getJson(
            '/api/v1/document-verification/' . $uuid
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'The document could not be found.')
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.status', 'NOT_FOUND')
            ->assertJsonPath('data.document', null)
            ->assertJsonPath('meta', []);
    }

    public function test_verified_document_returns_blockchain_details_in_existing_api_envelope(): void
    {
        $uuid = '22222222-2222-4222-8222-222222222222';

        $this->mock(DocumentVerificationService::class)
            ->shouldReceive('verify')
            ->once()
            ->with($uuid)
            ->andReturn([
                'valid' => true,
                'status' => 'VALID',
                'message' => 'This document is valid and was issued by the barangay.',
                'document' => [
                    'type' => 'Barangay Clearance',
                    'document_number' => 'DR-2026-000001',
                    'issued_at' => '2026-10-17T08:00:00Z',
                    'barangay' => 'Sample Barangay',
                ],
                'blockchain' => [
                    'status' => 'verified',
                    'verified' => true,
                    'message' => 'The document checksum matches its confirmed blockchain proof.',
                ],
            ]);

        $this->getJson('/api/v1/document-verification/' . $uuid)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.status', 'VALID')
            ->assertJsonPath('data.blockchain.status', 'verified')
            ->assertJsonPath('data.blockchain.verified', true)
            ->assertJsonPath('meta', []);
    }
}
