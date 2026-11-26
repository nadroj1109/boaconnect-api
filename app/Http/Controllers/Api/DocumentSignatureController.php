<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentFileResource;
use App\Models\DocumentRequest;
use App\Services\DocumentRequest\DocumentSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DocumentSignatureController extends Controller
{
    public function __construct(
        protected DocumentSignatureService $signature,
    ) {
    }

    public function store(
        Request $request,
        int $documentRequest,
    ): DocumentFileResource {

        $document =
            DocumentRequest::query()
                ->findOrFail(
                    $documentRequest
                );

        $file =
            $this->signature->sign(
                request: $document,
                user: $request->user(),
            );

        return new DocumentFileResource(
            $file
        );
    }

    /**
     * Upload a locally PNPKI-signed PDF.
     */
    public function uploadSignedDocument(
        Request $request,
        int $documentRequest,
    ): DocumentFileResource {
        $file = $request->file('signed_document');

        $contents = file_get_contents($file->getRealPath());

        Log::debug('PNPKI PDF Content Inspection', [
            'client_mime_type' => $file->getClientMimeType(),
            'detected_mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'first_20_bytes' => bin2hex(substr($contents, 0, 20)),
            'first_20_characters' => substr($contents, 0, 20),
            'starts_with_pdf' => str_starts_with($contents, '%PDF-'),
            'readable' => $contents !== false,
        ]);

        $validated = $request->validate([
            'signed_document' => [
                'required',
                'file',
                'max:20480',
                function ($attribute, $value, $fail) {
                    $contents = file_get_contents($value->getRealPath());

                    if (
                        $contents === false ||
                        preg_match('/%PDF-\d\.\d/', substr($contents, 0, 1024)) !== 1
                    ) {
                        $fail('The uploaded file must be a valid PDF.');
                    }
                },
            ],
        ]);

        $document = DocumentRequest::query()
            ->findOrFail($documentRequest);

        $file = $this->signature->uploadSignedDocument(
            request: $document,
            user: $request->user(),
            file: $validated['signed_document'],
        );

        return new DocumentFileResource($file);
    }

}
