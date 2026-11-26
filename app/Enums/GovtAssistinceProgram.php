<?php

namespace App\Enums;

use App\Traits\HasOptions;

enum GovtAssistinceProgram: string
{
    use HasOptions;

    case FourPs = '4ps';
    case TUPAD = 'tupad';
    case SLP = 'slp';
    case Others = 'others';

    public function label(): string
    {
        return match ($this) {
            self::FourPs => '4Ps',
            self::TUPAD => 'TUPAD',
            self::SLP => 'SLP',
            self::Others => 'Others',
        };
    }
}