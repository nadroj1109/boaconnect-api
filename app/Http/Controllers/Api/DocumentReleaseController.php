<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Http\Requests\DocumentRequest\ReleaseDocumentRequest;

use App\Http\Resources\DocumentRequestResource;

use App\Models\DocumentRequest;

use App\Services\DocumentRequest\DocumentReleaseService;

class DocumentReleaseController extends Controller
{
    public function __construct(
        protected DocumentReleaseService $release,
    ) {
    }

    /**
     * Release a generated document to the resident.
     */
    public function store(
        ReleaseDocumentRequest $request,
        int $documentRequest,
    ): DocumentRequestResource {
        $document = DocumentRequest::query()
            ->with([
                'documentType',
                'files',
            ])
            ->findOrFail($documentRequest);

        $document = $this->release->release(
            request: $document,
            user: $request->user(),
            remarks: $request->input('remarks'),
        );

        return new DocumentRequestResource(
            $document
        );
    }
}
