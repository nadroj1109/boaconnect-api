<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResidentMatchRequest;
use App\Http\Resources\Resident\ResidentMatchResource;
use App\Services\ResidentAuth\ResidentMatchingService;

class ResidentMatchController extends Controller
{
    public function __construct(
        protected ResidentMatchingService $service
    ) {}

    public function __invoke(
        ResidentMatchRequest $request
    ) {
        $response = $this->service->match(
            $request->verification_token
        );

        return response()->json([

            'success' => $response->success,

            'message' => $response->message,

            'resident' => $response->resident
                ? ResidentMatchResource::make($response->resident)
                : null,

        ]);
    }
}
