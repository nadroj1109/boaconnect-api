<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UpdateSignatureRequest;
use App\Services\Profile\ProfileService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProfileService $profile,
    ) {
    }

    /**
     * Display the authenticated user's profile.
     */
    public function show(
        Request $request
    ) {
        $user = $request->user();

        return $this->success(
            [
                'id' =>
                    $user->id,

                'name' =>
                    $user->name,

                'display_name' =>
                    $user->display_name,

                'email' =>
                    $user->email,

                'mobile_number' =>
                    $user->mobile_number,

                'has_signature' =>
                    $this->profile->hasSignature($user),

                'permissions' => $user->getAllPermissions()
                ->pluck('name')
                ->values(),
            ],
            'Profile retrieved successfully.'
        );
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(
        UpdateProfileRequest $request
    ) {
        $user = $this->profile->update(
            user: $request->user(),
            displayName:
                $request->validated('display_name'),
            email:
                $request->validated('email'),
        );

        return $this->success(
            [
                'id' =>
                    $user->id,

                'name' =>
                    $user->name,

                'display_name' =>
                    $user->display_name,

                'email' =>
                    $user->email,

                'mobile_number' =>
                    $user->mobile_number,

                'has_signature' =>
                    $this->profile->hasSignature($user),
            ],
            'Profile updated successfully.'
        );
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(
        UpdatePasswordRequest $request
    ) {
        $this->profile->updatePassword(
            user: $request->user(),
            password:
                $request->validated('password'),
        );

        return $this->success(
            null,
            'Password updated successfully.'
        );
    }

    /**
     * Update the authenticated user's signature.
     */
    public function updateSignature(
        UpdateSignatureRequest $request
    ) {
        $user = $this->profile->updateSignature(
            user: $request->user(),
            signature: $request->file('signature'),
        );

        return $this->success(
            [
                'has_signature' =>
                    $this->profile->hasSignature($user),
            ],
            'Signature updated successfully.'
        );
    }

    /**
     * Display the authenticated user's signature.
     */
    public function signature(
        Request $request
    ) {
        $signature = $this->profile->getSignature(
            $request->user(),
        );

        if (! $signature) {
            abort(404);
        }

        return response($signature)
            ->header(
                'Content-Type',
                'image/png'
            )
            ->header(
                'Cache-Control',
                'private, max-age=300'
            );
    }
}
