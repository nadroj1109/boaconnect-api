<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResidentRegistrationRequest;
use App\Http\Requests\Auth\ResidentRegistrationResubmissionRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Services\ResidentAuth\ResidentRegistrationService;
use App\Traits\ApiResponse;

class ResidentRegistrationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ResidentRegistrationService $service,
    ) {}

    public function __invoke(
        ResidentRegistrationRequest $request,
    ) {

        $response = $this->service->registerNewResident(
            $request->validated()
        );

        if (!$response->success) {
            return $this->error(
                $response->message,
                [],
                422
            );
        }

        return $this->success(
            $response->session
                ? AuthResource::make($response->session)
                : null,
            $response->message
        );
    }

    public function resubmit(
        ResidentRegistrationResubmissionRequest $request,
    ) {
        $resident = $request->user()->resident;

        if (! $resident) {
            return $this->error(
                'Resident record not found.',
                [],
                404
            );
        }

        $resident = $this->service->resubmit(
            $resident,
            $request->user(),
            $request->validated()
        );

        return $this->success(
            $resident,
            'Registration resubmitted successfully. Please wait for Secretary verification.'
        );
    }

}