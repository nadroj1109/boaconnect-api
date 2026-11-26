<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Http\Resources\DocumentFileResource;

use App\Models\DocumentRequest;

use App\Services\DocumentRequest\DocumentGenerationService;

use Illuminate\Http\Request;

class DocumentGenerationController extends Controller
{
    public function __construct(
        protected DocumentGenerationService $generation,
    ) {
    }

    /**
     * Generate the document for a document request.
     */
    public function store(
        Request $request,
        int $documentRequest,
    ): DocumentFileResource {
        $document = DocumentRequest::query()
            ->with('documentType')
            ->findOrFail($documentRequest);

        /*
         * Generation is an administrative operation.
         *
         * The actual permission will be enforced by the
         * route middleware using the existing permission system.
         *
         * DocumentGenerationService remains responsible for
         * determining whether the request is actually ready
         * for generation.
         */
        $file = $this->generation->generate(
            request: $document,
            user: $request->user(),
        );

        return new DocumentFileResource(
            $file
        );
    }
}
