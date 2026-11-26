<?php

namespace App\Http\Controllers\Api;

use App\Constants\Roles;
use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\User;
use App\Services\DocumentRequest\DocumentGenerationService;
use App\Services\DocumentRequest\DocumentQrCodeService;
use App\Services\DocumentRequest\DocumentVerificationService;
use App\Services\DocumentRequest\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DocumentPreviewController extends Controller
{
    public function __construct(
        protected DocumentGenerationService $generationService,
        protected DocumentQrCodeService $qrCodeService,
        protected DocumentVerificationService $verificationService,
        protected DocumentNumberService $documentNumberService,
    ) {
    }

    public function show(
        Request $request,
        DocumentRequest $documentRequest
    ): Response {

        $this->documentNumberService->ensure($documentRequest);

        $documentRequest->refresh();

        // Load required relationships
        $documentRequest->load([
            'documentType',
            'documentTemplate',
            'resident',
            'requestedFor.household',
            'barangay',
            'fields.documentTypeField',
        ]);

        // Validate document
        if (! $documentRequest->isForSignature()) {
            abort(422, 'This document request is not awaiting signature.');
        }

        if (! $documentRequest->documentTemplate) {
            abort(422, 'No document template has been configured.');
        }

        // Find active Chairperson
        $chairpersons = User::query()
            ->where('barangay_id', $documentRequest->barangay_id)
            ->where('is_active', true)
            ->whereHas('roles', function ($query) {
                $query->where('name', Roles::CHAIRPERSON);
            })
            ->get();

        if ($chairpersons->count() !== 1) {
            abort(
                422,
                'Exactly one active Chairperson must be assigned to this barangay before generating the document preview.'
            );
        }

        $chairperson = $chairpersons->first();

        // Persist verification UUID
        if (! $documentRequest->verification_uuid) {
            $documentRequest->verification_uuid = (string) Str::uuid();
            $documentRequest->save();
        }

        // Generate verification QR
        $verificationUrl = $this->verificationService->url(
            $documentRequest->verification_uuid
        );

        $verificationQr = $this->qrCodeService->generate(
            $verificationUrl
        );

        // Generate PDF
        $pdf = $this->generationService->generate(
            request: $documentRequest,
            signer: null,
            verificationQr: $verificationQr,
            signerName: $chairperson->name,
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="document-preview.pdf"',
        ]);
    }
}