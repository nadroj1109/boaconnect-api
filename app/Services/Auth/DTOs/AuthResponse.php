<?php

namespace App\Services\Auth\DTOs;

abstract class AuthResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
    ) {}

    public function failed(): bool
    {
        return ! $this->success;
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }
}