<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->onboarding_required) {
            return response()->json([
                'success' => false,
                'code' => 'ONBOARDING_REQUIRED',
                'message' => 'Please complete your onboarding before accessing this resource.',
            ], 403);
        }

        return $next($request);
    }
}
