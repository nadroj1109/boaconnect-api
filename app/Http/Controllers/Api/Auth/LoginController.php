<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\UserResource;
use App\Http\Resources\Resident\ResidentResource;
use App\Traits\ApiResponse;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuthService $authService
    ) {}

    public function __invoke(LoginRequest $request)
    {
        try {

            $result = $this->authService
                ->login($request->validated());

            return $this->success([
                'user' => new UserResource(
                    $result['user']
                ),

                'resident' => $result['user']->resident
                    ? new ResidentResource($result['user']->resident)
                    : null,
        
                'token' => $result['token']
            ], 'Login successful');

        } catch (\Exception $e) {

            return $this->error(
                $e->getMessage(),
                [],
                401
            );
        }
    }
}
