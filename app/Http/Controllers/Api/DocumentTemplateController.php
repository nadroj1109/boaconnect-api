<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentTemplate\StoreDocumentTemplateRequest;
use App\Http\Requests\DocumentTemplate\UpdateDocumentTemplateRequest;
use App\Http\Resources\DocumentTemplateResource;
use App\Models\DocumentTemplate;
use App\Services\DocumentTemplateService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class DocumentTemplateController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DocumentTemplateService $service
    ) {
    }

    public function index(Request $request)
    {
        $paginator = $this->service->index(
            $request->all()
        );

        return $this->paginated(
            $paginator,
            DocumentTemplateResource::collection(
                $paginator
            )
        );
    }

    public function store(
        StoreDocumentTemplateRequest $request
    ) {
        $documentTemplate = $this->service->store(
            $request->validated()
        );

        return $this->success(
            new DocumentTemplateResource(
                $documentTemplate
            ),
            'Document template created successfully.'
        );
    }

    public function show(
        DocumentTemplate $documentTemplate
    ) {
        return $this->success(
            new DocumentTemplateResource(
                $this->service->show(
                    $documentTemplate
                )
            )
        );
    }

    public function update(
        UpdateDocumentTemplateRequest $request,
        DocumentTemplate $documentTemplate
    ) {
        $documentTemplate = $this->service->update(
            $documentTemplate,
            $request->validated()
        );

        return $this->success(
            new DocumentTemplateResource(
                $documentTemplate
            ),
            'Document template updated successfully.'
        );
    }

    public function destroy(
        DocumentTemplate $documentTemplate
    ) {
        $this->service->destroy(
            $documentTemplate
        );

        return $this->success(
            null,
            'Document template deleted successfully.'
        );
    }

    public function available()
    {
        return $this->success(
            DocumentTemplateResource::collection(
                $this->service->available()
            ),
            'Available document templates retrieved successfully.'
        );
    }
}
