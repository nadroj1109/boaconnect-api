<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendVerificationRequest;
use App\Http\Requests\Auth\VerifyVerificationRequest;

use App\Enums\VerificationChannel;
use App\Enums\VerificationPurpose;

use App\Services\Verification\VerificationService;

class VerificationController extends Controller
{
    public function __construct(
        protected VerificationService $verification
    ) {}

    /**
     * Send verification code.
     */
    public function send(
        SendVerificationRequest $request
    ) {

        $purpose = $request->enum(
            'purpose',
            VerificationPurpose::class
        );

        if (! $this->verification->canResend(

            recipient: $request->recipient,

            purpose: $purpose,

        )) {

            return response()->json([

                'success' => false,

                'message' => sprintf(

                    'Please wait %d seconds before requesting another verification code.',

                    config('verification.otp.resend_after')

                ),

            ], 429);

        }

        $this->verification->send(

            recipient: $request->recipient,

            channel: $request->enum(
                'channel',
                VerificationChannel::class
            ),

            purpose: $purpose,

        );

        return response()->json([

            'success' => true,

            'message' => 'Verification code sent successfully.',

            'expires_in' =>

                config('verification.otp.expires') * 60,

            'resend_after' =>

                config('verification.otp.resend_after'),

        ]);

    }

    /**
     * Verify verification code.
     */
    public function verify(
        VerifyVerificationRequest $request
    ) {

        $response = $this->verification->verify(

            recipient: $request->recipient,

            purpose: $request->enum(
                'purpose',
                VerificationPurpose::class
            ),

            code: $request->code,

        );

        return response()->json([

            'success' =>

                $response->success,

            'message' =>

                $response->message,

            'verification_token' =>

                $response->verification?->verification_token,

            'expires_at' => $response->verification?->expires_at?->toIso8601String(),

            'expires_in' =>

                config('verification.otp.expires') * 60,

        ]);

    }
}
