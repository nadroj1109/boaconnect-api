<?php

namespace App\Services\DocumentRequest;

use App\Enums\DocumentRequestStatus;
use App\Models\DocumentFile;
use App\Models\BlockchainAnchor;
use App\Services\Blockchain\BlockchainProofService;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

class DocumentVerificationService
{
    public function __construct(
        protected BlockchainProofService $blockchain,
    ) {
    }

    /**
     * Generate the public document verification URL.
     */
    public function url(string $uuid): string
    {
        return rtrim(
            config('app.frontend_url'),
            '/'
        ) . '/verify/document/' . $uuid;
    }

    public function verify(
        string $uuid,
    ): array {
        $file = DocumentFile::query()
            ->with([
                'documentRequest.documentType',
                'documentRequest.barangay',
            ])
            ->where('uuid', $uuid)
            ->first();

        if (! $file) {
            return [
                'valid' => false,
                'status' => 'NOT_FOUND',
                'message' =>
                    'The document could not be found.',
                'document' => null,
            ];
        }

        $request = $file->documentRequest;

        if (! $request) {
            return [
                'valid' => false,
                'status' => 'INVALID',
                'message' =>
                    'This document could not be verified.',
                'document' => null,
            ];
        }

        if (
            ! in_array(
                $request->status,
                [
                    DocumentRequestStatus::RELEASED,
                    DocumentRequestStatus::DOWNLOADED,
                ],
                true
            )
        ) {
            if (
                $request->status ===
                DocumentRequestStatus::CANCELLED
            ) {
                return [
                    'valid' => false,
                    'status' => 'CANCELLED',
                    'message' =>
                        'This document has been cancelled.',
                    'document' => null,
                ];
            }

            if (
                $request->status ===
                DocumentRequestStatus::REJECTED
            ) {
                return [
                    'valid' => false,
                    'status' => 'REJECTED',
                    'message' =>
                        'This document request was rejected.',
                    'document' => null,
                ];
            }

            return [
                'valid' => false,
                'status' => 'NOT_RELEASED',
                'message' =>
                    'This document has not been released.',
                'document' => null,
            ];
        }

        return [
            'valid' => true,
            'status' => 'VALID',
            'message' =>
                'This document is valid and was issued by the barangay.',
            'document' => [
                'type' => $request->documentType?->name,
                'document_number' => $request->request_no,
                'issued_at' => $request->released_at,
                'barangay' => $request->barangay?->barangay,
            ],
            'blockchain' => $this->verifyBlockchain($file),
        ];
    }

    private function verifyBlockchain(DocumentFile $file): array
    {
        $unavailable = [
            'status' => 'unavailable',
            'verified' => false,
            'message' => 'Blockchain verification is temporarily unavailable.',
        ];

        $mismatch = [
            'status' => 'mismatch',
            'verified' => false,
            'message' => 'The document integrity or blockchain proof could not be verified.',
        ];

        // First verify the actual PDF against its stored checksum.
        try {
            $prepared = $this->blockchain->prepareDocumentProof($file);
        } catch (RuntimeException $exception) {
            // This check is local: the file is missing, its metadata is invalid,
            // or its actual contents do not match the stored checksum.
            return $mismatch;
        }

        // Only accept a confirmed anchor for this exact document and proof version.
        $anchor = BlockchainAnchor::query()
            ->where('document_file_id', $file->id)
            ->where('proof_type', BlockchainAnchor::TYPE_DOCUMENT)
            ->where('proof_version', $prepared['proof_version'])
            ->where('status', BlockchainAnchor::STATUS_CONFIRMED)
            ->first();

        if (! $anchor) {
            return [
                'status' => 'not_anchored',
                'verified' => false,
                'message' => 'No confirmed blockchain proof is available for this document.',
            ];
        }

        // The anchor's recorded hash must match the actual PDF bytes.
        if (! hash_equals(
            strtolower($anchor->proof_hash),
            $prepared['proof_hash']
        )) {
            return $mismatch;
        }

        // Inspect the actual chain; the database status alone is not proof.
        try {
            $inspection = $this->blockchain->inspectAnchorOnChain($anchor);
        } catch (Throwable $exception) {
            // Keep RPC, network, and configuration errors out of the public response.
            report($exception);

            return $unavailable;
        }

        if (($inspection['state'] ?? null) !== 'matching') {
            return $mismatch;
        }

        return [
            'status' => 'verified',
            'verified' => true,
            'message' => 'The document checksum matches its confirmed blockchain proof.',
        ];
    }
}