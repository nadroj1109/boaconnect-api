<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Http\Requests\DocumentRequest\StoreDocumentRequest;

use App\Http\Resources\DocumentRequestResource;

use App\Models\Resident;
use App\Models\DocumentRequest;

use App\Services\DocumentRequest\DocumentAuthorizationService;
use App\Services\DocumentRequest\DocumentRequestService;

use Illuminate\Http\Request;

class DocumentRequestController extends Controller
{
    public function __construct(
        protected DocumentRequestService $documentRequests,
        protected DocumentAuthorizationService $authorization,
    ) {
    }

    /**
     * Display document requests belonging to
     * the authenticated resident.
     */
    public function index(Request $request)
    {
        $resident = $request->user()->resident;

        abort_if(
            ! $resident,
            403,
            'Resident account is required.'
        );

        $perPage = min(
            max(
                (int) $request->input('per_page', 15),
                1
            ),
            50
        );

        $requests = $this->documentRequests->forResident(
            $resident,
            $perPage
        );

        return DocumentRequestResource::collection(
            $requests
        );
    }

    /**
     * Store a new document request.
     */
    public function store(
        StoreDocumentRequest $request
    ): DocumentRequestResource {
        //     \Log::info(
        //     'Document Request Incoming Data',
        //     $request->all()
        // );
        $user = $request->user();

        $requester = $user->resident;

        abort_if(
            ! $requester,
            403,
            'Resident account is required.'
        );

        $requestedFor = Resident::findOrFail(
            $request->integer(
                'requested_for_resident_id'
            )
        );

        $barangayId = $requester->barangay_id;

        abort_if(
            ! $barangayId,
            422,
            'The resident is not associated with a barangay.'
        );

        $documentRequest =
            $this->documentRequests->create(
                user: $user,
                requester: $requester,

                requestedFor: $requestedFor,

                documentTypeId:
                    $request->integer(
                        'document_type_id'
                    ),

                barangayId:
                    $barangayId,

                purpose:
                    $request->string(
                        'purpose'
                    )->toString(),

                deliveryMethod:
                    $request->string(
                        'delivery_method'
                    )->toString(),

                remarks:
                    $request->input('remarks'),

                fields:
                    $request->input(
                        'fields',
                        []
                    ),

                attachments:
                    $request->file(
                        'attachments',
                        []
                    ),
            );

        $documentRequest->load([
            'documentType',
            'resident',
            'requestedFor',
            'payments',
            'files',
            'logs',
            'fields.documentTypeField',
            'attachments.requirement',
            'attachments.uploadedBy',
        ]);

        return new DocumentRequestResource(
            $documentRequest
        );
    }

    /**
     * Display a specific document request.
     */
    public function show(
        Request $request,
        DocumentRequest $documentRequest
    ): DocumentRequestResource {

        $resident = $request->user()->resident;

        abort_if(
            ! $resident,
            403,
            'Resident account is required.'
        );

        $this->authorization->authorizeRequest(
            $request->user(),
            $documentRequest
        );

        $documentRequest->load([
            'documentType',
            'documentTemplate',
            'resident',
            'requestedFor',
            'payments',
            'files',
            'logs.performedBy',
            'fields.documentTypeField',
            'attachments.requirement',
            'attachments.uploadedBy',
        ]);

        return new DocumentRequestResource(
            $documentRequest
        );
    }
}
