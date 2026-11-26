<?php

namespace App\Enums;

enum ResidentLoginResult: string
{
    case SUCCESS = 'success';
    case INVALID_CREDENTIALS = 'invalid_credentials';
    case ACCOUNT_DISABLED = 'account_disabled';
    case RESIDENT_NOT_LINKED = 'resident_not_linked';

    public function message(): string
    {
        return match ($this) {
            self::SUCCESS => 'Login successful.',
            self::INVALID_CREDENTIALS => 'Invalid email/mobile number or password.',
            self::ACCOUNT_DISABLED => 'Your account has been deactivated.',
            self::RESIDENT_NOT_LINKED => 'Resident profile is no longer linked to this account.',
        };
    }
}