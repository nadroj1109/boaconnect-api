<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\UserResource;
use App\Http\Resources\Resident\ResidentResource;
use App\Traits\ApiResponse;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;

class MeController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuthService $authService
    ) {}

    public function __invoke(Request $request)
    {
        $user = $this->authService
            ->me($request->user());

        return $this->success([
            'user' => new UserResource($user),

            'resident' => $user->resident
                ? new ResidentResource($user->resident)
                : null,
        ]);
    }
}
