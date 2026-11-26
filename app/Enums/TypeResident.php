<?php

namespace App\Enums;

use App\Traits\HasOptions;

enum TypeResident: string
{
    use HasOptions;

    case Migrant = 'migrant';
    case NonMigrant = 'non_migrant';
    case Transient = 'transient';

    public function label(): string
    {
        return match ($this) {
            self::Migrant => 'Migrant',
            self::NonMigrant => 'Non-Migrant',
            self::Transient => 'Transient',
        };
    }
}