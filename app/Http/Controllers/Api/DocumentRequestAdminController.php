<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentRequest\DocumentRequestIndexRequest;
use App\Http\Resources\DocumentRequestResource;
use App\Services\DocumentRequest\DocumentRequestService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class DocumentRequestAdminController extends Controller
{
    use ApiResponse;
    
    public function __construct(
        protected DocumentRequestService $documentRequests,
    ) {
    }

    /**
     * Display document requests available to
     * the authenticated administrative user.
     */
    public function index(
        DocumentRequestIndexRequest $request
    ) {
        $requests = $this->documentRequests->paginate(
            user: $request->user(),
            filters: $request->validated(),
        );

        return $this->paginated(
            $requests,

            DocumentRequestResource::collection(
                $requests
            ),

            'Document requests retrieved successfully.'
        );
    }

    /**
     * Display a document request available to
     * the authenticated administrative user.
     */
    public function show(
        Request $request,
        int $id
    ) {
        $documentRequest =
            $this->documentRequests->findForAdmin(
                user: $request->user(),
                id: $id,
            );

        return $this->success(
            new DocumentRequestResource(
                $documentRequest
            ),
            'Document request retrieved successfully.'
        );
    }
}
