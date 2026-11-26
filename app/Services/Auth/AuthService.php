<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function login(array $credentials): array
    {
        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ])) {
            throw new \Exception('Invalid credentials');
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            throw new \Exception(
                'Your account is inactive. Please contact your administrator.'
            );
        }

        $this->loadAuthRelations($user);

        $token = $user->createToken(
            $credentials['device_name'] ?? 'web'
        )->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function me(User $user): User
    {
        return $this->loadAuthRelations($user);
    }

    private function loadAuthRelations(User $user): User
    {
        return $user->load([
            'roles',
            'permissions',
            'resident.residentSectors',
            'resident.region',
            'resident.province',
            'resident.city',
            'resident.barangay',
            'resident.ethnicity',
            'resident.household',
            'resident.householdRegistration.requestedHousehold',
            'resident.residentSectors',
        ]);
    }
}