<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentRequestSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentRequest\StoreWalkInDocumentRequest;
use App\Http\Resources\DocumentRequestResource;
use App\Models\DocumentRequest;
use App\Models\Resident;
use App\Services\DocumentRequest\DocumentPaymentService;
use App\Services\DocumentRequest\DocumentRequestService;

class DocumentWalkInRequestController extends Controller
{
    public function __construct(
        protected DocumentRequestService $documentRequests,
        protected DocumentPaymentService $documentPayments,
    ) {
    }

    /**
     * Store a new walk-in document request.
     */
    public function store(
        StoreWalkInDocumentRequest $request
    ): DocumentRequestResource {

        $user = $request->user();

        $requester = Resident::findOrFail(
            $request->integer('requester_id')
        );

        $requestedFor = Resident::findOrFail(
            $request->integer(
                'requested_for_resident_id'
            )
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
                    $request->integer(
                        'barangay_id'
                    ),

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

                source:
                    DocumentRequestSource::WALK_IN,
            );

        /*
         * Move the walk-in request into the
         * existing payment stage.
         */
        $documentRequest->markAwaitingPayment();
        $documentRequest->save();

        /*
         * Create and record the cash payment.
         */
        $payment =
            $this->documentPayments->createCashPayment(
                $documentRequest
            );

        $payment =
            $this->documentPayments->recordCashPayment(
                payment: $payment,
                receivedBy: $user,
                referenceNo:
                    $request->input('reference_no'),
            );

        $documentRequest->load([
            'documentType',
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
