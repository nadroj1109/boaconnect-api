<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuthService $authService
    ) {}

    public function __invoke(Request $request)
    {
        $this->authService
            ->logout($request->user());

        return $this->success(
            null,
            'Logout successful'
        );
    }
}
