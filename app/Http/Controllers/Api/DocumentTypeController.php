<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentType\DocumentTypeIndexRequest;
use App\Http\Requests\DocumentType\StoreDocumentTypeRequest;
use App\Http\Requests\DocumentType\UpdateDocumentTypeRequest;
use App\Http\Resources\DocumentTypeResource;
use App\Models\DocumentType;
use App\Services\DocumentTypeService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class DocumentTypeController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DocumentTypeService $service
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(DocumentTypeIndexRequest $request)
    {
        $documentTypes = $this->service->paginate(

            $request->validated()

        );

        return $this->paginated(

            $documentTypes,

            DocumentTypeResource::collection($documentTypes),

            'Document Types retrieved successfully.'

        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDocumentTypeRequest $request)
    {
        $documentType =
            $this->service->store(
                $request->validated()
            );

        return $this->success(
            new DocumentTypeResource(
                $documentType
            ),

            'Document Type created successfully.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(DocumentType $documentType)
    {
        return $this->success(

            new DocumentTypeResource(

                $this->service->show(
                    $documentType
                )

            )

        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDocumentTypeRequest $request, DocumentType $documentType)
    {
        $documentType =
            $this->service->update(

                $documentType,

                $request->validated()

            );

        return $this->success(

            new DocumentTypeResource(
                $documentType
            ),

            'Document Type updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DocumentType $documentType)
    {
        $this->service->destroy(
            $documentType
        );

        return $this->success(
            [],

            'Document Type deleted successfully.'
        );
    }

    public function available(Request $request)
    {
        return $this->success(

            DocumentTypeResource::collection(
                $this->service->available($request->integer('resident_id'))
            ),

            'Available document types retrieved successfully.'
        );
    }
}
