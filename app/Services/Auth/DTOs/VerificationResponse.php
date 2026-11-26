<?php

namespace App\Services\Auth\DTOs;

use App\Models\VerificationCode;

class VerificationResponse extends AuthResponse
{
    public function __construct(
        bool $success,
        string $message,
        public readonly ?VerificationCode $verification = null,
    ) {
        parent::__construct($success, $message);
    }
}