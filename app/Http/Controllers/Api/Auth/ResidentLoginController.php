<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResidentLoginRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Services\ResidentAuth\ResidentLoginService;

class ResidentLoginController extends Controller
{
    public function __construct(
        protected ResidentLoginService $service,
    ) {}

    public function __invoke(
        ResidentLoginRequest $request,
    ) {
        $response = $this->service->login(

            username:
                $request->username,

            password:
                $request->password,

        );

        return response()->json([

            'success' =>
                $response->success,

            'message' =>
                $response->message,

            'data' =>
                $response->success && $response->session
                    ? AuthResource::make(
                        $response->session
                    )
                    : null,

        ], $response->success ? 200 : 422);
    }
}