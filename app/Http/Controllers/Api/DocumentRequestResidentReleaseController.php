<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentRequestResource;
use App\Models\DocumentRequest;
use App\Services\DocumentRequest\DocumentAuthorizationService;
use App\Services\DocumentRequest\DocumentReleaseService;
use App\Traits\ApiResponse;

class DocumentRequestResidentReleaseController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DocumentReleaseService $release,
        protected DocumentAuthorizationService $authorization,
    ) {
    }

    /**
     * Mark a digitally downloaded document as released.
     */
    public function store(
        int $documentRequest,
    ): DocumentRequestResource {
        $user = request()->user();

        $document = DocumentRequest::query()
            ->with([
                'documentType',
                'files',
            ])
            ->findOrFail($documentRequest);

        $this->authorization
            ->authorizeReleaseAfterDownload(
                user: $user,
                request: $document,
            );

        $document = $this->release
            ->releaseAfterDownload(
                request: $document,
                user: $user,
            );

        return new DocumentRequestResource(
            $document
        );
    }
}
