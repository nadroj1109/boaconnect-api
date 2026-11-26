<?php

namespace App\Services\Verification\Contracts;

interface VerificationDriver
{
    public function send(
        string $recipient,
        string $code,
        string $purpose,
    ): void;
}