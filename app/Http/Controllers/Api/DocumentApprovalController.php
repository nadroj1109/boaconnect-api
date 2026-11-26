<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Http\Requests\DocumentRequest\ApproveDocumentRequest;
use App\Http\Requests\DocumentRequest\RejectDocumentRequest;
use App\Http\Requests\DocumentRequest\ReviewDocumentRequest;

use App\Http\Resources\DocumentRequestResource;

use App\Models\DocumentRequest;

use App\Services\DocumentRequest\DocumentApprovalService;

use Illuminate\Http\Request;

class DocumentApprovalController extends Controller
{
    public function __construct(
        protected DocumentApprovalService $approval,
    ) {
    }

    /**
     * Place a pending document request under review.
     */
    // public function review(
    //     ReviewDocumentRequest $request,
    //     int $documentRequest,
    // ): DocumentRequestResource {
    //     $document = DocumentRequest::findOrFail(
    //         $documentRequest
    //     );

    //     $document = $this->approval->review(
    //         $document,
    //         $request->user(),
    //     );

    //     return new DocumentRequestResource(
    //         $document
    //     );
    // }

    /**
     * Approve a document request currently under review.
     */
    public function approve(
        ApproveDocumentRequest $request,
        int $documentRequest,
    ): DocumentRequestResource {
        $document = DocumentRequest::findOrFail(
            $documentRequest
        );

        $document = $this->approval->approve(
            request: $document,
            user: $request->user(),
            remarks: $request->input('remarks'),
        );

        return new DocumentRequestResource(
            $document
        );
    }

    // for-signature
    public function forSignature(
        Request $request,
        int $documentRequest,
    ): DocumentRequestResource {

        $document =
            DocumentRequest::query()
                ->findOrFail(
                    $documentRequest
                );

        $document =
            $this->approval->forSignature(
                request: $document,
                user: $request->user(),
            );

        return new DocumentRequestResource(
            $document
        );
    }

    /**
     * Reject a document request currently under review.
     */
    public function reject(
        RejectDocumentRequest $request,
        int $documentRequest,
    ): DocumentRequestResource {
        $document = DocumentRequest::findOrFail(
            $documentRequest
        );

        $document = $this->approval->reject(
            request: $document,
            user: $request->user(),
            remarks: $request->string(
                'remarks'
            )->toString(),
        );

        return new DocumentRequestResource(
            $document
        );
    }
}
