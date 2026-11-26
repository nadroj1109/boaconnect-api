<?php

namespace App\Services\Payments;

use App\Models\DocumentPayment;

use App\Services\DocumentRequest\DocumentPaymentService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayMongoWebhookService
{
    public function __construct(
        protected DocumentPaymentService $payments,
    ) {
    }

    public function handle(
        Request $request
    ) {

        $payload = $request->all();

        $eventType =
            data_get(
                $payload,
                'data.attributes.type'
            );

        $eventData =
            data_get(
                $payload,
                'data.attributes.data'
            );

        $eventId =
            data_get(
                $payload,
                'data.id'
            );

        Log::info(
            'PayMongo webhook received',
            [
                'event_id' =>
                    $eventId,

                'event_type' =>
                    $eventType,
            ]
        );

        match ($eventType) {

            'checkout_session.payment.paid' =>
                $this->handleCheckoutPaid(
                    $eventData,
                    $payload
                ),

            default =>
                Log::info(
                    'Unhandled PayMongo webhook event',
                    [
                        'event_type' =>
                            $eventType,
                    ]
                ),
        };

        return response()->json([
            'success' => true,
        ]);
    }

    protected function handleCheckoutPaid(
        ?array $checkout,
        array $payload
    ): void {

        if (! $checkout) {
            return;
        }

        $checkoutSessionId =
            data_get(
                $checkout,
                'id'
            );

        $attributes =
            data_get(
                $checkout,
                'attributes',
                []
            );

        $referenceNumber =
            data_get(
                $attributes,
                'reference_number'
            );

        $metadata =
            data_get(
                $attributes,
                'metadata',
                []
            );

        $paymentId =
            $metadata['document_payment_id']
            ?? null;

        Log::info(
            'PayMongo checkout payment paid',
            [
                'checkout_session_id' =>
                    $checkoutSessionId,

                'reference_number' =>
                    $referenceNumber,

                'document_payment_id' =>
                    $paymentId,
            ]
        );

        $payment = null;

        if ($paymentId) {

            $payment =
                DocumentPayment::find(
                    $paymentId
                );
        }

        if (! $payment && $checkoutSessionId) {

            $payment =
                DocumentPayment::query()
                    ->where(
                        'checkout_session_id',
                        $checkoutSessionId
                    )
                    ->first();
        }

        if (! $payment && $referenceNumber) {

            $payment =
                DocumentPayment::query()
                    ->whereHas(
                        'documentRequest',
                        function ($query) use (
                            $referenceNumber
                        ) {
                            $query->where(
                                'request_no',
                                $referenceNumber
                            );
                        }
                    )
                    ->pending()
                    ->latest()
                    ->first();
        }

        if (! $payment) {

            Log::warning(
                'PayMongo payment could not be matched',
                [
                    'checkout_session_id' =>
                        $checkoutSessionId,

                    'reference_number' =>
                        $referenceNumber,

                    'document_payment_id' =>
                        $paymentId,
                ]
            );

            return;
        }

        if (! $payment->isPayMongo()) {

            Log::warning(
                'Webhook matched a non-PayMongo payment',
                [
                    'payment_id' =>
                        $payment->id,

                    'provider' =>
                        $payment->provider?->value,
                ]
            );

            return;
        }

        /*
         * Idempotency:
         *
         * PayMongo may retry webhook delivery.
         *
         * markPaid() already handles an already-paid
         * payment safely.
         */

        $providerReference =
            $checkoutSessionId;

        $referenceNo =
            $referenceNumber;

        $this->payments->markPaid(

            payment: $payment,

            providerReference:
                $providerReference,

            referenceNo:
                $referenceNo,

            gatewayResponse:
                $payload,

        );
    }
}