<?php

namespace App\Enums;

use App\Traits\HasOptions;

enum Nationality: string
{
    use HasOptions;

    case FilipinoCitizen = 'filipino_citizen';
    case DualCitizen = 'dual_citizen';
    case ForeignCitizen = 'foreign_citizen';
    case NoCitizenship = 'no_citizenship';

    public function label(): string
    {
        return match ($this) {
            self::FilipinoCitizen => 'Filipino Citizen',
            self::DualCitizen => 'Dual Citizen',
            self::ForeignCitizen => 'Foreign Citizen',
            self::NoCitizenship => 'No Citizenship',
        };
    }
}