<?php

namespace App\Services\Verification\Drivers;

use Exception;

use App\Services\Verification\Contracts\VerificationDriver;

class SmsVerificationDriver implements VerificationDriver
{
    public function send(
        string $recipient,
        string $code
    ): void
    {
        throw new Exception(
            'SMS driver not implemented.'
        );
    }
}