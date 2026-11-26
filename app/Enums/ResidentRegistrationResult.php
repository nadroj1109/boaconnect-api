<?php

namespace App\Enums;

enum ResidentRegistrationResult:string
{
    case SUCCESS = 'success';

    case MATCH_FAILED = 'match_failed';

    public function message(): string
    {
        return match ($this) {

            self::SUCCESS =>
                'Registration completed successfully.',

            self::MATCH_FAILED =>
                'Unable to register resident.',

        };
    }
}
