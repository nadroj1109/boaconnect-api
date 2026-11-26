<?php

namespace App\Services\ResidentAuth;

use App\Models\User;

use App\Enums\ResidentLoginResult;

use App\Services\Auth\DTOs\AuthSession;
use App\Services\Auth\DTOs\ResidentLoginResponse;

class ResidentLoginService
{
    public function login(
        string $username,
        string $password,
    ): ResidentLoginResponse {

        /*
        |--------------------------------------------------------------------------
        | Find User
        |--------------------------------------------------------------------------
        */

        $user = User::findForLogin($username);

        if (! $user) {

            return new ResidentLoginResponse(

                false,

                ResidentLoginResult::INVALID_CREDENTIALS->message(),

            );

        }

        /*
        |--------------------------------------------------------------------------
        | Verify Password
        |--------------------------------------------------------------------------
        */

        if (! $user->verifyPassword($password)) {

            return new ResidentLoginResponse(

                false,

                ResidentLoginResult::INVALID_CREDENTIALS->message(),

            );

        }

        /*
        |--------------------------------------------------------------------------
        | Verify Account Status
        |--------------------------------------------------------------------------
        */

        if (! $user->isActive()) {

            return new ResidentLoginResponse(

                false,

                ResidentLoginResult::ACCOUNT_DISABLED->message(),

            );

        }

        /*
        |--------------------------------------------------------------------------
        | Verify Resident Link
        |--------------------------------------------------------------------------
        */

        if (! $user->resident) {

            return new ResidentLoginResponse(

                false,

                ResidentLoginResult::RESIDENT_NOT_LINKED->message(),

            );

        }

        /*
        |--------------------------------------------------------------------------
        | Issue Token
        |--------------------------------------------------------------------------
        */

        $token = $user->issueToken();

        return new ResidentLoginResponse(

            true,

            ResidentLoginResult::SUCCESS->message(),

            session: AuthSession::fromUser(

                user: $user,

                token: $token,

            ),

        );
    }
}