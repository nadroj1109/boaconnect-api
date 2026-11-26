<?php

namespace App\Enums;

use App\Traits\HasOptions;

enum Complexion: string
{
    use HasOptions;

    case Fair = 'fair';
    case Medium = 'medium';
    case Dark = 'dark';

    public function label(): string
    {
        return match ($this) {
            self::Fair => 'Fair',
            self::Medium => 'Medium',
            self::Dark => 'Dark',
        };
    }
}