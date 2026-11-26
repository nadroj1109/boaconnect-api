<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ActivateAccountRequest;
use App\Services\Auth\ActivationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivationController extends Controller
{
    public function __construct(

        protected ActivationService $service

    ) {}

    /**
     * Verify activation token.
     */
    public function verify(string $token): JsonResponse
    {
        $user = $this->service->verify($token);

        return response()->json([

            'success' => true,

            'message' => 'Activation link is valid.',

            'data' => [

                'display_name' => $user->display_name,

                'email' => $user->email,

            ],

        ]);

    }

    /**
     * Activate account.
     */
    public function activate(

        ActivateAccountRequest $request

    ): JsonResponse {

        $user = $this->service->activate(

            $request->token,

            $request->password

        );

        return response()->json([

            'success' => true,

            'message' => 'Account activated successfully.',

            'data' => [

                'id' => $user->id,

                'display_name' => $user->display_name,

            ],

        ]);

    }
}
