<?php

namespace App\Enums;

use App\Traits\HasOptions;

enum TenureStatus: string
{
    use HasOptions;

    case Owner = 'owner';
    case Renter = 'renter';
    case Others = 'others';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Renter => 'Renter',
            self::Others => 'Others',
        };
    }
}