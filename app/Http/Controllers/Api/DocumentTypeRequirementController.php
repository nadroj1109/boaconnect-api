<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentTypeRequirement\StoreDocumentTypeRequirementRequest;
use App\Http\Requests\DocumentTypeRequirement\UpdateDocumentTypeRequirementRequest;
use App\Http\Resources\DocumentTypeRequirementResource;
use App\Models\DocumentType;
use App\Models\DocumentTypeRequirement;
use App\Services\DocumentTypeRequirementService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class DocumentTypeRequirementController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DocumentTypeRequirementService $service
    ) {}

    public function index(
        DocumentType $documentType
    ) {
        return $this->success(
            DocumentTypeRequirementResource::collection(
                $this->service->index(
                    $documentType
                )
            ),
            'Document Type Requirements retrieved successfully.'
        );
    }

    public function store(
        StoreDocumentTypeRequirementRequest $request,
        DocumentType $documentType
    ) {
        \Log::info('Document Type Requirement Store', [
            'route_document_type_id' =>
                $documentType->id,

            'route_document_type_name' =>
                $documentType->name,

            'route_parameter' =>
                $request->route('documentType'),

            'request_document_type_id' =>
                $request->input('document_type_id'),
        ]);

        $requirement =
            $this->service->store(
                $documentType,
                $request->validated()
            );

        return $this->success(
            new DocumentTypeRequirementResource(
                $requirement
            ),
            'Document Type Requirement created successfully.'
        );
    }

    public function show(
        DocumentType $documentType,
        DocumentTypeRequirement $requirement
    ) {
        $this->ensureBelongsToDocumentType(
            $documentType,
            $requirement
        );

        return $this->success(
            new DocumentTypeRequirementResource(
                $this->service->show(
                    $documentType,
                    $requirement
                )
            )
        );
    }

    public function update(
        UpdateDocumentTypeRequirementRequest $request,
        DocumentType $documentType,
        DocumentTypeRequirement $requirement
    ) {
        $this->ensureBelongsToDocumentType(
            $documentType,
            $requirement
        );

        $requirement =
            $this->service->update(
                $requirement,
                $request->validated()
            );

        return $this->success(
            new DocumentTypeRequirementResource(
                $requirement
            ),
            'Document Type Requirement updated successfully.'
        );
    }

    public function destroy(
        DocumentType $documentType,
        DocumentTypeRequirement $requirement
    ) {
        $this->ensureBelongsToDocumentType(
            $documentType,
            $requirement
        );

        $this->service->destroy(
            $requirement
        );

        return $this->success(
            [],
            'Document Type Requirement deleted successfully.'
        );
    }

    protected function ensureBelongsToDocumentType(
        DocumentType $documentType,
        DocumentTypeRequirement $requirement
    ): void {
        abort_unless(
            $requirement->document_type_id ===
                $documentType->id,
            404
        );
    }
}
