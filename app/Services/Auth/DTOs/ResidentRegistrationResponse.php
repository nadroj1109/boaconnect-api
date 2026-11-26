<?php

namespace App\Services\Auth\DTOs;

class ResidentRegistrationResponse extends AuthResponse
{
    public function __construct(
        bool $success,
        string $message,
        public readonly ?AuthSession $session = null,
    ) {
        parent::__construct($success, $message);
    }
}