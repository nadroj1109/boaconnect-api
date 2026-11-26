<?php

namespace App\Enums;

enum VerificationResult: string
{
    case VERIFIED = 'verified';
    case INVALID_CODE = 'invalid_code';
    case EXPIRED = 'expired';
    case MAX_ATTEMPTS = 'max_attempts';
    case ALREADY_VERIFIED = 'already_verified';
    case NOT_FOUND = 'not_found';

    public function message(): string
    {
        return match ($this) {
            self::VERIFIED => 'Verification successful.',
            self::INVALID_CODE => 'The verification code is incorrect.',
            self::EXPIRED => 'The verification code has expired.',
            self::MAX_ATTEMPTS => 'Maximum verification attempts exceeded.',
            self::ALREADY_VERIFIED => 'The verification code was already verified.',
            self::NOT_FOUND => 'The verification code was not found.',
        };
    }
}