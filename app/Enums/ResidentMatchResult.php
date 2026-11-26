<?php

namespace App\Enums;

enum ResidentMatchResult: string
{
    case MATCHED = 'matched';
    case NOT_FOUND = 'not_found';
    case INVALID_TOKEN = 'invalid_token';
    case TOKEN_EXPIRED = 'token_expired';
    case TOKEN_ALREADY_USED = 'token_already_used';
    case ALREADY_REGISTERED = 'already_registered';

    public function message(): string
    {
        return match ($this) {
            self::MATCHED => 'Resident record found.',
            self::NOT_FOUND => 'No resident record matched the verified contact information.',
            self::INVALID_TOKEN => 'The verification token is invalid.',
            self::TOKEN_EXPIRED => 'The verification token has expired.',
            self::TOKEN_ALREADY_USED => 'The verification token has already been used.',
            self::ALREADY_REGISTERED => 'This resident already has a BOAConnect account.',
        };
    }
}