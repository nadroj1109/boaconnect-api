<?php

namespace App\Services\ResidentAuth;

use App\Models\Resident;
use App\Models\VerificationCode;

use App\Enums\ResidentMatchResult;

use App\Services\Auth\DTOs\ResidentMatchResponse;

class ResidentMatchingService
{
    public function match(
        string $verificationToken
    ): ResidentMatchResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Find Verification
        |--------------------------------------------------------------------------
        */

        $verification = VerificationCode::query()

            ->where(
                'verification_token',
                $verificationToken
            )

            ->first();

        if (! $verification) {

            return new ResidentMatchResponse(

                false,

                ResidentMatchResult::INVALID_TOKEN->message()

            );

        }

        /*
        |--------------------------------------------------------------------------
        | Validate Verification State
        |--------------------------------------------------------------------------
        */

        if (! $verification->isVerified()) {

            return new ResidentMatchResponse(

                false,

                ResidentMatchResult::INVALID_TOKEN->message()

            );

        }

        if ($verification->isConsumed()) {

            return new ResidentMatchResponse(

                false,

                ResidentMatchResult::TOKEN_ALREADY_USED->message()

            );

        }

        if ($verification->isExpired()) {

            return new ResidentMatchResponse(

                false,

                ResidentMatchResult::TOKEN_EXPIRED->message()

            );

        }

        /*
        |--------------------------------------------------------------------------
        | Find Resident
        |--------------------------------------------------------------------------
        */

        $resident = Resident::findByVerification(

            verification: $verification,

            with: [

                'barangay',

                'household',

            ]

        );

        if (! $resident) {

            return new ResidentMatchResponse(

                false,

                ResidentMatchResult::NOT_FOUND->message()

            );

        }

        /*
        |--------------------------------------------------------------------------
        | Already Registered?
        |--------------------------------------------------------------------------
        */

        if ($resident->hasAccount()) {

            return new ResidentMatchResponse(

                false,

                ResidentMatchResult::ALREADY_REGISTERED->message(),

                resident: $resident,

                verification: $verification,

            );

        }

        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        return new ResidentMatchResponse(

            true,

            ResidentMatchResult::MATCHED->message(),

            resident: $resident,

            verification: $verification,

        );
    }
}