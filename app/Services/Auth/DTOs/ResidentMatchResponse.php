<?php

namespace App\Services\Auth\DTOs;

use App\Models\Resident;
use App\Models\VerificationCode;

class ResidentMatchResponse extends AuthResponse
{
    public function __construct(
        bool $success,
        string $message,
        public readonly ?Resident $resident = null,
        public readonly ?VerificationCode $verification = null,
    ) {
        parent::__construct($success, $message);
    }
}