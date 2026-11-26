<?php

namespace App\Enums;

enum ComplaintSource: string
{
    case RESIDENT = 'resident';
    case STAFF = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::RESIDENT => 'Resident',
            self::STAFF => 'Staff',
        };
    }
}
