<?php

namespace App\Services\Verification;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use App\Models\VerificationCode;

use App\Enums\VerificationChannel;
use App\Enums\VerificationPurpose;
use App\Enums\VerificationResult;

use App\Services\Verification\Factories\VerificationDriverFactory;
use App\Services\Auth\DTOs\VerificationResponse;

class VerificationService
{
    /**
     * Send a verification code.
     */
    public function send(
        string $recipient,
        VerificationChannel $channel,
        VerificationPurpose $purpose
    ): VerificationCode {

        return DB::transaction(function () use (
            $recipient,
            $channel,
            $purpose
        ) {

            $this->invalidatePreviousCodes(
                $recipient,
                $purpose
            );

            $code = $this->generateCode();

            $verification = VerificationCode::create([

                'recipient' => $recipient,

                'channel' => $channel,

                'purpose' => $purpose,

                'code' => Hash::make($code),

                'expires_at' => now()->addMinutes(
                    config('verification.otp.expires')
                ),

            ]);

            VerificationDriverFactory::make($channel)
                ->send(
                    recipient: $recipient,
                    code: $code,
                    purpose: $purpose->value,
                );

            return $verification;

        });

    }

    /**
     * Verify a code.
     */
    public function verify(
        string $recipient,
        VerificationPurpose $purpose,
        string $code
    ): VerificationResponse {

        $verification = $this->findActiveCode(
            $recipient,
            $purpose
        );

        if (! $verification) {

            return new VerificationResponse(

                false,

                VerificationResult::NOT_FOUND->message(),

            );

        }

        if ($verification->isVerified()) {

            return new VerificationResponse(

                false,

                VerificationResult::ALREADY_VERIFIED->message(),

            );

        }

        if ($verification->isExpired()) {

            return new VerificationResponse(

                false,

                VerificationResult::EXPIRED->message(),

            );

        }

        if (
            $verification->attempts >=
            config('verification.otp.max_attempts')
        ) {

            return new VerificationResponse(

                false,

                VerificationResult::TOO_MANY_ATTEMPTS->message(),

            );

        }

        if (! Hash::check(
            $code,
            $verification->code
        )) {

            $verification->incrementAttempts();

            return new VerificationResponse(

                false,

                VerificationResult::INVALID_CODE->message(),

            );

        }

        $verification->markVerified();
        $verification->refresh();

        return new VerificationResponse(

            true,

            VerificationResult::VERIFIED->message(),

            verification: $verification,

        );

    }

    /**
     * Can resend another verification code.
     */
    public function canResend(
        string $recipient,
        VerificationPurpose $purpose
    ): bool {

        $verification = $this->findActiveCode(
            $recipient,
            $purpose
        );

        if (! $verification) {

            return true;

        }

        return now()->greaterThanOrEqualTo(

            $verification
                ->created_at
                ->addSeconds(
                    config('verification.otp.resend_after')
                )

        );

    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function generateCode(): string
    {
        $length = config('verification.otp.length');

        return str_pad(

            (string) random_int(
                0,
                (10 ** $length) - 1
            ),

            $length,

            '0',

            STR_PAD_LEFT

        );
    }

    protected function invalidatePreviousCodes(
        string $recipient,
        VerificationPurpose $purpose
    ): void {

        VerificationCode::query()

            ->where('recipient', $recipient)

            ->where('purpose', $purpose)

            ->whereNull('verified_at')

            ->delete();

    }

    protected function findActiveCode(
        string $recipient,
        VerificationPurpose $purpose
    ): ?VerificationCode {

        return VerificationCode::query()

            ->where('recipient', $recipient)

            ->where('purpose', $purpose)

            ->latest()

            ->first();

    }
}