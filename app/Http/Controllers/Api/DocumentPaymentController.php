<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Http\Requests\DocumentRequest\CreateDocumentPaymentRequest;
use App\Http\Requests\DocumentRequest\RecordCashPaymentRequest;

use App\Http\Resources\DocumentPaymentResource;

use App\Models\DocumentRequest;

use App\Services\DocumentRequest\DocumentAuthorizationService;
use App\Services\DocumentRequest\DocumentPaymentService;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentProvider;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentPaymentController extends Controller
{
    public function __construct(
        protected DocumentPaymentService $payments,
        protected DocumentAuthorizationService $authorization,
    ) {
    }

    /**
     * Create or retrieve the pending payment
     * for an approved document request.
     */
    public function store(
        CreateDocumentPaymentRequest $request,
        int $documentRequest,
    ): DocumentPaymentResource {

        $document = DocumentRequest::query()
            ->with('documentType')
            ->findOrFail($documentRequest);

        $this->authorization->authorizeRequest(
            $request->user(),
            $document,
        );

        $paymentMethod = $request->enum(
            'payment_method',
            PaymentMethod::class
        );

        if ($paymentMethod === PaymentMethod::OTC) {

            $payment = $this->payments->createCashPayment(
                request: $document,
            );

        } else {

            $payment = $this->payments->createPayment(
                request: $document,

                paymentMethod:
                    $paymentMethod,

                user:
                    $request->user(),
            );

        }

        return new DocumentPaymentResource(
            $payment
        );
    }

    /**
     * Display the current pending payment.
     */
    public function show(
        Request $request,
        int $documentRequest,
    ): ?DocumentPaymentResource {
        $document = DocumentRequest::findOrFail(
            $documentRequest
        );

        $this->authorization->authorizeRequest(
            $request->user(),
            $document,
        );

        $payment = $this->payments->pendingPayment(
            $document
        );

        if (! $payment) {
            return null;
        }

        return new DocumentPaymentResource(
            $payment
        );
    }

    /**
     * Cancel the pending payment.
     */
    public function cancel(
        Request $request,
        int $documentRequest,
    ): ?DocumentPaymentResource {
        $document = DocumentRequest::findOrFail(
            $documentRequest
        );

        $this->authorization->authorizeRequest(
            $request->user(),
            $document,
        );

        $payment = $this->payments->pendingPayment(
            $document
        );

        if (! $payment) {
            return null;
        }

        $payment = $this->payments->cancel(
            payment: $payment,
            user: $request->user(),
        );

        return new DocumentPaymentResource(
            $payment
        );
    }

    /**
     * Verify the PayMongo payment directly and
     * update the local payment record.
     *
     * This is a fallback when the webhook has not
     * updated the payment yet.
     */
    public function confirm(
        Request $request,
        int $documentRequest,
    ): DocumentPaymentResource {

        $document = DocumentRequest::findOrFail(
            $documentRequest
        );

        $this->authorization->authorizeRequest(
            $request->user(),
            $document,
        );

        $payment = $this->payments->pendingPayment(
            $document
        );

        if (! $payment) {

            /*
             * It may already have been marked paid
             * by the webhook.
             */
            $payment = $document->payments()
                ->where(
                    'status',
                    \App\Enums\PaymentStatus::PAID
                )
                ->latest()
                ->first();

            if (! $payment) {
                abort(
                    404,
                    'No payment record was found.'
                );
            }

            return new DocumentPaymentResource(
                $payment
            );
        }

        $payment =
            $this->payments
                ->confirmPayMongoPayment(
                    $payment
                );

        return new DocumentPaymentResource(
            $payment
        );
    }

    /**
     * Record a pending cash / over-the-counter payment.
     */
    public function recordCash(
        RecordCashPaymentRequest $request,
        int $documentRequest,
    ): DocumentPaymentResource {

        $document = DocumentRequest::query()
            ->with('documentType')
            ->findOrFail(
                $documentRequest
            );

        $this->authorization->authorizeRequest(
            $request->user(),
            $document,
        );

        $payment = $this->payments->pendingPayment(
            $document
        );

        if (! $payment) {
            throw ValidationException::withMessages([
                'payment' =>
                    'No pending payment was found for this document request.',
            ]);
        }

        if (
            $payment->provider !==
                PaymentProvider::MANUAL ||
            $payment->payment_method !==
                PaymentMethod::OTC
        ) {
            throw ValidationException::withMessages([
                'payment' =>
                    'The pending payment is not a cash / over-the-counter payment.',
            ]);
        }

        $payment = $this->payments->recordCashPayment(
            payment: $payment,
            receivedBy: $request->user(),
            referenceNo: $request->input(
                'reference_no'
            ),
            paidAt: $request->date('paid_at'),
        );

        return new DocumentPaymentResource(
            $payment
        );
    }
}
