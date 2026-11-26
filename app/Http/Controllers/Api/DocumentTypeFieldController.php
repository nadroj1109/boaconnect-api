<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentTypeField\StoreDocumentTypeFieldRequest;
use App\Http\Requests\DocumentTypeField\UpdateDocumentTypeFieldRequest;
use App\Http\Resources\DocumentTypeFieldResource;
use App\Models\DocumentType;
use App\Models\DocumentTypeField;
use App\Services\DocumentTypeFieldService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DocumentTypeFieldController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DocumentTypeFieldService $service
    ) {
    }

    public function index(
        DocumentType $documentType
    ) {
        $fields =
            $this->service->index(
                $documentType
            );

        return $this->success(
            DocumentTypeFieldResource::collection(
                $fields
            )
        );
    }

    public function store(
        StoreDocumentTypeFieldRequest $request,
        DocumentType $documentType
    ) {
        $field =
            $this->service->store(
                $documentType,
                $request->validated()
            );

        return $this->success(
            new DocumentTypeFieldResource(
                $field
            ),
            'Document Type Field created successfully.'
        );
    }

    public function update(
        UpdateDocumentTypeFieldRequest $request,
        DocumentType $documentType,
        DocumentTypeField $documentTypeField
    ) {
        $field =
            $this->service->update(
                $documentTypeField,
                $request->validated()
            );

        // Log::info('Document Type Field update result', [
        //     'class' => get_class($field),
        //     'id' => $field->id,
        //     'attributes' => $field->getAttributes(),
        //     'option_source' => $field->option_source,
        // ]);

        return $this->success(
            new DocumentTypeFieldResource(
                $field
            ),
            'Document Type Field updated successfully.'
        );
    }

    public function destroy(
        DocumentType $documentType,
        DocumentTypeField $documentTypeField
    ) {
        $this->service->destroy(
            $documentTypeField
        );

        return $this->success(
            null,
            'Document Type Field deleted successfully.'
        );
    }
}
