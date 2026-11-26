<?php

namespace App\Services\DocumentRequest;

use App\Enums\DocumentRequestStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;

use App\Jobs\CreatePaymentBlockchainAnchor;

use App\Models\DocumentPayment;
use App\Models\DocumentRequest;
use App\Models\User;

use App\Services\Payments\PayMongoService;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentPaymentService
{
    public function __construct(
        protected PayMongoService $paymongo,
    ) {
    }

    /**
     * Prepare a payment for an approved document request.
     *
     * This creates our local payment record and moves the
     * document request into the awaiting-payment state.
     *
     * It does NOT mark the payment as paid.
     */
    public function createPayment(
        DocumentRequest $request,
        PaymentMethod $paymentMethod,
        ?User $user = null,
    ): DocumentPayment {

        /*
        |--------------------------------------------------------------------------
        | Lock and validate request
        |--------------------------------------------------------------------------
        */

        $request = $this->lockRequest($request);

        if (/*! $request->isApproved() &&*/ ! $request->isAwaitingPayment()) {

            throw ValidationException::withMessages([
                'status' =>
                    'Only approved requests can be submitted for payment.',
            ]);
        }

        if (! $request->requiresPayment()) {

            throw ValidationException::withMessages([
                'payment' =>
                    'This document request does not require payment.',
            ]);
        }

        if (! $request->documentType) {

            throw ValidationException::withMessages([
                'document_type' =>
                    'The document type could not be determined.',
            ]);
        }

        $amount = $request->documentType->fee;

        if ($amount <= 0) {

            throw ValidationException::withMessages([
                'amount' =>
                    'The document fee is invalid.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Check existing pending payment
        |--------------------------------------------------------------------------
        */

        $existingPayment =
            $request->payments()
                ->where(
                    'status',
                    PaymentStatus::PENDING
                )
                ->latest()
                ->first();

        if ($existingPayment) {

            return $existingPayment;
        }

        /*
        |--------------------------------------------------------------------------
        | Create local payment
        |--------------------------------------------------------------------------
        */

        $payment = $request->payments()->create([

            'amount' =>
                $amount,

            'provider' =>
                PaymentProvider::PAYMONGO,

            'payment_method' =>
                $paymentMethod,

            'status' =>
                PaymentStatus::PENDING,

        ]);

        /*
        |--------------------------------------------------------------------------
        | Create PayMongo checkout session
        |--------------------------------------------------------------------------
        */

        try {

            $checkout =
                $this->paymongo
                    ->createCheckoutSession([

                        'line_items' => [

                            [
                                'name' =>
                                    $request
                                        ->documentType
                                        ->name,

                                'amount' =>
                                    (int) round(
                                        $amount * 100
                                    ),

                                'currency' =>
                                    'PHP',

                                'quantity' =>
                                    1,
                            ],

                        ],

                        'payment_method_types' => [

                            'card',
                            'gcash',
                            'qrph',

                        ],

                        'description' =>
                            "Barangay document request {$request->request_no}",

                        'reference_number' =>
                            $request->request_no,

                        'metadata' => [

                            'document_request_id' =>
                                (string) $request->id,

                            'document_payment_id' =>
                                (string) $payment->id,

                        ],

                        'success_url' =>
                            config('app.frontend_url') .
                            '/payment/success',

                        'cancel_url' =>
                            config('app.frontend_url') .
                            '/payment/cancelled',

                    ]);

        } catch (\Throwable $e) {

            $payment->markFailed([
                'error' =>
                    $e->getMessage(),
            ]);

            $payment->save();

            throw $e;
        }

        /*
        |--------------------------------------------------------------------------
        | Extract checkout information
        |--------------------------------------------------------------------------
        */

        $checkoutSessionId =
            data_get(
                $checkout,
                'id'
            );

        $checkoutUrl =
            data_get(
                $checkout,
                'attributes.checkout_url'
            );

        if (
            ! $checkoutSessionId ||
            ! $checkoutUrl
        ) {

            $payment->markFailed([
                'error' =>
                    'PayMongo did not return a valid checkout session.',

                'response' =>
                    $checkout,
            ]);

            $payment->save();

            throw new \RuntimeException(
                'Invalid PayMongo checkout session response.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Save checkout information
        |--------------------------------------------------------------------------
        */

        $payment->markCheckoutCreated(
            $checkoutSessionId,
            $checkoutUrl
        );

        $payment->gateway_response =
            $checkout;

        $payment->save();

        /*
        |--------------------------------------------------------------------------
        | Move request to awaiting payment
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use (
            $request,
            $user
        ) {

            $request->markAwaitingPayment();

            $request->save();

            $request->logs()->create([

                'status' =>
                    DocumentRequestStatus::AWAITING_PAYMENT,

                'remarks' =>
                    'Payment required for document request.',

                'performed_by' =>
                    $user?->id,

            ]);

        });

        return $payment->fresh([
            'documentRequest',
        ]);
    }

    public function createCashPayment(
        DocumentRequest $request
    ): DocumentPayment {
        return DB::transaction(function () use ($request) {

            $request = DocumentRequest::query()
                ->with('documentType')
                ->lockForUpdate()
                ->findOrFail($request->id);

            if (! $request->isAwaitingPayment()) {
                throw ValidationException::withMessages([
                    'document_request' =>
                        'This document request is not awaiting payment.',
                ]);
            }

            if (! $request->requiresPayment()) {
                throw ValidationException::withMessages([
                    'document_request' =>
                        'This document request does not require payment.',
                ]);
            }

            $amount = $request->documentType->fee;

            if ($amount === null || $amount <= 0) {
                throw ValidationException::withMessages([
                    'document_request' =>
                        'This document does not have a valid payment amount.',
                ]);
            }

            $existingPayment = $request->payments()
                ->where('status', PaymentStatus::PENDING)
                ->where(
                    'provider',
                    PaymentProvider::MANUAL
                )
                ->where(
                    'payment_method',
                    PaymentMethod::OTC
                )
                ->latest('id')
                ->first();

            if ($existingPayment) {
                return $existingPayment;
            }

            return DocumentPayment::create([
                'document_request_id' => $request->id,
                'amount' => $amount,
                'provider' => PaymentProvider::MANUAL,
                'payment_method' => PaymentMethod::OTC,
                'status' => PaymentStatus::PENDING,
            ]);
        });
    }

    /**
     * Record the successful payment confirmation.
     *
     * This method must only be called after the PayMongo
     * webhook has been verified by the webhook handling layer.
     */
    public function markPaid(
        DocumentPayment $payment,
        string $providerReference,
        ?string $referenceNo = null,
        ?array $gatewayResponse = null,
    ): DocumentPayment {
        $result = DB::transaction(function () use (
            $payment,
            $providerReference,
            $referenceNo,
            $gatewayResponse,
        ) {
            $payment = $this->lockPayment($payment);

            if ($payment->isPaid()) {
                return $payment->fresh(['documentRequest']);
            }

            if (! $payment->isPending()) {
                throw ValidationException::withMessages([
                    'payment' => 'Only pending payments can be marked as paid.',
                ]);
            }

            $request = DocumentRequest::query()
                ->whereKey($payment->document_request_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $request->isAwaitingPayment()) {
                throw ValidationException::withMessages([
                    'status' => 'The document request is not awaiting payment.',
                ]);
            }

            $payment->markPaid(
                providerReference: $providerReference,
                referenceNo: $referenceNo,
                gatewayResponse: $gatewayResponse,
            );

            $payment->save();

            $request->markPaid();
            $request->save();

            $request->logs()->create([
                'status' => DocumentRequestStatus::PAYMENT_RECEIVED,
                'remarks' => 'Payment received and verified.',
                'performed_by' => null,
            ]);

            return $payment->fresh(['documentRequest']);
        });

        if ($result->isPaid() && $result->paid_at) {
            CreatePaymentBlockchainAnchor::dispatch($result->id);
        }

        return $result;
    }
    
    public function recordCashPayment(
        DocumentPayment $payment,
        User $receivedBy,
        ?string $referenceNo = null,
        ?Carbon $paidAt = null,
    ): DocumentPayment {
        $result = DB::transaction(function () use (
            $payment,
            $receivedBy,
            $referenceNo,
            $paidAt,
        ) {
            $payment = DocumentPayment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($payment->isPaid()) {
                return $payment->fresh([
                    'documentRequest',
                    'receivedBy',
                ]);
            }

            if (! $payment->isPending()) {
                throw ValidationException::withMessages([
                    'payment' => 'Only pending payments can be recorded as cash.',
                ]);
            }

            if ($payment->provider !== PaymentProvider::MANUAL) {
                throw ValidationException::withMessages([
                    'payment' => 'Only manual payments can be recorded as cash.',
                ]);
            }

            if ($payment->payment_method !== PaymentMethod::OTC) {
                throw ValidationException::withMessages([
                    'payment' => 'This payment is not configured for over-the-counter payment.',
                ]);
            }

            $request = DocumentRequest::query()
                ->lockForUpdate()
                ->findOrFail($payment->document_request_id);

            if (! $request->isAwaitingPayment()) {
                throw ValidationException::withMessages([
                    'document_request' => 'This document request is not awaiting payment.',
                ]);
            }

            $payment->markPaid(
                providerReference: null,
                referenceNo: $referenceNo,
                gatewayResponse: null,
            );

            $payment->paid_at = $paidAt ?? now();
            $payment->received_by = $receivedBy->id;
            $payment->reference_no = $referenceNo;

            $payment->save();

            $request->markPaid();
            $request->save();

            $request->logs()->create([
                'status' => $request->status,
                'remarks' => 'Cash payment received and recorded.',
                'performed_by' => $receivedBy->id,
            ]);

            return $payment->fresh([
                'documentRequest',
                'receivedBy',
            ]);
        });

        if ($result->isPaid() && $result->paid_at) {
            CreatePaymentBlockchainAnchor::dispatch($result->id);
        }

        return $result;
    }

    /**
     * Verify a PayMongo Checkout Session and mark
     * the local payment as paid.
     *
     * This is used as a fallback when the webhook
     * confirmation is unavailable.
     */
    public function confirmPayMongoPayment(
        DocumentPayment $payment
    ): DocumentPayment {

        if (! $payment->isPayMongo()) {

            throw ValidationException::withMessages([
                'payment' =>
                    'This payment is not a PayMongo payment.',
            ]);
        }

        if (! $payment->hasCheckoutSession()) {

            throw ValidationException::withMessages([
                'payment' =>
                    'This payment does not have a PayMongo checkout session.',
            ]);
        }

        if ($payment->isPaid()) {
            return $payment->fresh([
                'documentRequest',
            ]);
        }

        if (! $payment->isPending()) {

            throw ValidationException::withMessages([
                'payment' =>
                    'Only pending PayMongo payments can be confirmed.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Retrieve authoritative payment state from PayMongo
        |--------------------------------------------------------------------------
        */

        $checkout =
            $this->paymongo
                ->retrieveCheckoutSession(
                    $payment->checkout_session_id
                );

        $checkoutId =
            data_get(
                $checkout,
                'id'
            );

        /*
        |--------------------------------------------------------------------------
        | Verify that this is the same Checkout Session
        |--------------------------------------------------------------------------
        */

        if (
            ! $checkoutId ||
            $checkoutId !==
                $payment->checkout_session_id
        ) {

            throw ValidationException::withMessages([
                'payment' =>
                    'The PayMongo checkout session could not be verified.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify our local payment metadata
        |--------------------------------------------------------------------------
        */

        $metadata =
            data_get(
                $checkout,
                'attributes.metadata',
                []
            );

        $metadataPaymentId =
            data_get(
                $metadata,
                'document_payment_id'
            );

        if (
            (string) $metadataPaymentId !==
            (string) $payment->id
        ) {

            throw ValidationException::withMessages([
                'payment' =>
                    'The PayMongo payment does not match this payment record.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Find the successful PayMongo payment
        |--------------------------------------------------------------------------
        */

        $payments =
            data_get(
                $checkout,
                'attributes.payments',
                []
            );

        $paidPayment = collect($payments)
            ->first(
                fn ($paymongoPayment) =>
                    data_get(
                        $paymongoPayment,
                        'attributes.status'
                    ) === 'paid'
            );

        /*
        |--------------------------------------------------------------------------
        | Payment has not actually completed yet
        |--------------------------------------------------------------------------
        */

        if (! $paidPayment) {

            throw ValidationException::withMessages([
                'payment' =>
                    'The PayMongo payment has not been completed yet.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Extract provider information
        |--------------------------------------------------------------------------
        */

        $providerReference =
            data_get(
                $paidPayment,
                'id'
            );

        $referenceNo =
            data_get(
                $checkout,
                'attributes.reference_number'
            );

        /*
        |--------------------------------------------------------------------------
        | Mark our local payment as paid
        |--------------------------------------------------------------------------
        */

        return $this->markPaid(
            payment:
                $payment,

            providerReference:
                $providerReference,

            referenceNo:
                $referenceNo,

            gatewayResponse:
                $checkout,
        );
    }

    /**
     * Mark a pending payment as failed.
     */
    public function markFailed(
        DocumentPayment $payment,
        ?array $gatewayResponse = null,
    ): DocumentPayment {
        return DB::transaction(function () use (
            $payment,
            $gatewayResponse,
        ) {

            $payment = $this->lockPayment($payment);

            if (! $payment->isPending()) {
                throw ValidationException::withMessages([
                    'payment' =>
                        'Only pending payments can be marked as failed.',
                ]);
            }

            $payment->markFailed(
                $gatewayResponse
            );

            $payment->save();

            return $payment->fresh([
                'documentRequest',
            ]);
        });
    }

    /**
     * Cancel a pending payment.
     */
    public function cancel(
        DocumentPayment $payment,
        User $user,
    ): DocumentPayment {
        return DB::transaction(function () use ($payment, $user) {

            $payment = $this->lockPayment($payment);

            if (! $payment->isPending()) {
                throw ValidationException::withMessages([
                    'payment' =>
                        'Only pending payments can be cancelled.',
                ]);
            }

            $payment->markCancelled();

            $payment->save();

            $documentRequest = $payment->documentRequest()
                ->lockForUpdate()
                ->firstOrFail();

            $documentRequest->logs()->create([
                'status' => $documentRequest->status,
                'remarks' => 'Payment selection cancelled.',
                'performed_by' => $user->id,
            ]);

            return $payment->fresh([
                'documentRequest',
            ]);
        });
    }

    /**
     * Find the active payment for a request.
     */
    public function pendingPayment(
        DocumentRequest $request
    ): ?DocumentPayment {
        return $request->payments()
            ->where(
                'status',
                PaymentStatus::PENDING
            )
            ->latest()
            ->first();
    }

    /**
     * Find a payment by provider reference.
     */
    public function findByProviderReference(
        string $providerReference
    ): ?DocumentPayment {
        return DocumentPayment::query()
            ->where(
                'provider_reference',
                $providerReference
            )
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Locking
    |--------------------------------------------------------------------------
    */

    protected function lockRequest(
        DocumentRequest $request
    ): DocumentRequest {
        return DocumentRequest::query()
            ->with('documentType')
            ->whereKey($request->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function lockPayment(
        DocumentPayment $payment
    ): DocumentPayment {
        return DocumentPayment::query()
            ->whereKey($payment->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}