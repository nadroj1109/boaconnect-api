<?php

namespace App\Enums;

use App\Traits\HasOptions;

enum Gender: string
{
    use HasOptions;

    case Lesbian = 'lesbian';
    case Gay = 'gay';
    case Bisexual = 'bisexual';
    case Transgender = 'transgender';
    case Queer = 'queer';
    case Intersex = 'intersex';
    case Asexual = 'asexual';
    case Others = 'others';

    public function label(): string
    {
        return match ($this) {
            self::Lesbian => 'Lesbian',
            self::Gay => 'Gay',
            self::Bisexual => 'Bisexual',
            self::Transgender => 'Transgender',
            self::Queer => 'Queer',
            self::Intersex => 'Intersex',
            self::Asexual => 'Asexual',
            self::Others => 'Others',
        };
    }
}