<?php

namespace App\Enums;

use App\Traits\HasOptions;

enum CivilStatus: string
{
    use HasOptions;

    case Single = 'single';
    case Married = 'married';
    case CommonLaw = 'common_law';
    case Widowed = 'widowed';
    case Divorced = 'divorced';
    case Separated = 'separated';
    case Annulled = 'annulled';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single/Never Married',
            self::Married => 'Married',
            self::CommonLaw => 'Common Law/Live-in',
            self::Widowed => 'Widowed',
            self::Divorced => 'Divorced',
            self::Separated => 'Separated',
            self::Annulled => 'Annulled',
            self::Unknown => 'Unknown',
        };
    }
}