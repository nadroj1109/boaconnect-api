<?php

namespace App\Enums;

use App\Traits\HasOptions;

enum YesNo: string
{
    use HasOptions;

    case Yes = 'yes';
    case No = 'no';

    public function label(): string
    {
        return match ($this) {
            self::Yes => 'Yes',
            self::No => 'No',
        };
    }
}