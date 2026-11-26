<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayMongoService
{
    protected string $secretKey;

    protected string $webhookSecret;

    protected string $baseUrl;

    public function __construct()
    {
        $this->secretKey =
            config('services.paymongo.secret_key');

        $this->webhookSecret =
            config('services.paymongo.webhook_secret');

        $this->baseUrl =
            config('services.paymongo.base_url', 'https://api.paymongo.com');
    }

    public function createCheckoutSession(
        array $attributes
    ): array {

        $response = Http::withBasicAuth(
                $this->secretKey,
                ''
            )
            ->acceptJson()
            ->post(
                "{$this->baseUrl}/v2/checkout_sessions",
                [
                    'data' => [
                        'attributes' => $attributes,
                    ],
                ]
            );

        if ($response->failed()) {

            throw new RuntimeException(
                'Unable to create PayMongo checkout session: ' .
                $response->body()
            );
        }

        return $response->json('data');
    }

    /**
 * Retrieve a PayMongo Checkout Session.
 */
public function retrieveCheckoutSession(
    string $checkoutSessionId
): array {

    $response = Http::withBasicAuth(
            $this->secretKey,
            ''
        )
        ->acceptJson()
        ->get(
            "{$this->baseUrl}/v1/checkout_sessions/{$checkoutSessionId}"
        );

    if ($response->failed()) {

        throw new RuntimeException(
            'Unable to retrieve PayMongo checkout session: ' .
            $response->body()
        );
    }

    return $response->json('data');
}

    /**
     * Verify that an incoming webhook
     * was sent by PayMongo.
     */
    public function verifyWebhook(
        Request $request
    ): bool {

        $signatureHeader =
            $request->header(
                'Paymongo-Signature'
            );

        if (
            empty($signatureHeader) ||
            empty($this->webhookSecret)
        ) {
            return false;
        }

        /*
         * Parse:
         *
         * t=timestamp,
         * te=test_signature,
         * li=live_signature
         */
        $signatures = [];

        foreach (
            explode(',', $signatureHeader)
            as $part
        ) {

            [$key, $value] =
                array_pad(
                    explode(
                        '=',
                        trim($part),
                        2
                    ),
                    2,
                    null
                );

            if (
                $key !== null &&
                $value !== null
            ) {
                $signatures[$key] =
                    $value;
            }
        }

        $timestamp =
            $signatures['t']
            ?? null;

        if (! $timestamp) {
            return false;
        }

        /*
         * Determine whether this application
         * is currently using PayMongo test mode
         * or live mode.
         */
        $isTestMode =
            str_starts_with(
                $this->secretKey,
                'sk_test_'
            );

        $providedSignature =
            $isTestMode
                ? ($signatures['te'] ?? null)
                : ($signatures['li'] ?? null);

        if (empty($providedSignature)) {
            return false;
        }

        /*
         * IMPORTANT:
         *
         * Use the original raw request body.
         * Do not use $request->all() or
         * json_encode($request->all()) here.
         */
        $signedPayload =
            $timestamp .
            '.' .
            $request->getContent();

        $expectedSignature =
            hash_hmac(
                'sha256',
                $signedPayload,
                $this->webhookSecret
            );

        return hash_equals(
            $expectedSignature,
            $providedSignature
        );
    }
}