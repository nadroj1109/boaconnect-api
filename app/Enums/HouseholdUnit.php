<?php

namespace App\Enums;

use App\Traits\HasOptions;

enum HouseholdUnit: string
{
    use HasOptions;

    case SingleHouse = 'single_house';
    case Duplex = 'duplex';
    case Townhouse = 'townhouse';
    case Condominium = 'condominium';
    case Apartment = 'apartment';
    case Others = 'others';

    public function label(): string
    {
        return match ($this) {
            self::SingleHouse => 'Single House',
            self::Duplex => 'Duplex',
            self::Townhouse => 'Townhouse/Rowhouse',
            self::Condominium => 'Condominium',
            self::Apartment => 'Apartment',
            self::Others => 'Others',
        };
    }
}