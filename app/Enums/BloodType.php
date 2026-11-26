<?php

namespace App\Enums;

use App\Traits\HasOptions;

enum BloodType: string
{
    use HasOptions;

    case A_POSITIVE = 'A+';
    case O_POSITIVE = 'O+';
    case B_POSITIVE = 'B+';
    case AB_POSITIVE = 'AB+';

    case A_NEGATIVE = 'A-';
    case O_NEGATIVE = 'O-';
    case B_NEGATIVE = 'B-';
    case AB_NEGATIVE = 'AB-';

    case UNKNOWN = 'Unknown';

    public function label(): string
    {
        return $this->value;
    }
}