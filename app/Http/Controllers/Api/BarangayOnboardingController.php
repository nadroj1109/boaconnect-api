<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteBarangayOnboardingRequest;
use App\Http\Resources\User\UserResource;
use App\Models\SitioPurok;
use App\Services\BarangayOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarangayOnboardingController extends Controller
{
    public function __construct(
        private readonly BarangayOnboardingService $onboardingService
    ) {}

    /**
     * Return the authenticated user's onboarding status.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'onboarding_required' => (bool) $user->onboarding_required,
                'is_active' => (bool) $user->is_active,
                'resident_id' => $user->resident_id,
                'barangay_id' => $user->barangay_id,
                'official_position' => $user->officialPosition?->name,
            ],
        ]);
    }

    /**
     * Complete onboarding for a provisioned barangay user.
     */
    public function complete(
        CompleteBarangayOnboardingRequest $request
    ): JsonResponse {
        $user = $this->onboardingService->complete(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Your onboarding has been completed successfully.',
            'data' => new UserResource($user),
        ]);
    }

    public function sitioPuroks(Request $request): JsonResponse
    {
        $user = $request->user();

        $sitioPuroks = SitioPurok::query()
            ->where('barangay_id', $user->barangay_id)
            ->where('is_active', true)
            ->orderBy('sitio_purok')
            ->get(['id', 'sitio_purok', 'is_active']);

        return response()->json([
            'success' => true,
            'data' => $sitioPuroks,
        ]);
    }
}