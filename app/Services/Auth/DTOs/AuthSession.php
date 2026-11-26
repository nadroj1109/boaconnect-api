<?php

namespace App\Services\Auth\DTOs;

use App\Models\User;
use App\Models\Resident;
use App\Models\Barangay;

class AuthSession
{
    public function __construct(
        public readonly ?string $token,
        public readonly User $user,
        public readonly Resident $resident,
        public readonly ?Barangay $barangay = null,
    ) {}

    public static function fromUser(
        User $user,
        ?string $token = null,
    ): self {
        return new self(
            token: $token,
            user: $user,
            resident: $user->resident,
            barangay: $user->barangay,
        );
    }
}