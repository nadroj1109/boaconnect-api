<?php

namespace App\Enums;

use App\Traits\HasOptions;

enum Sector: string
{
    use HasOptions;

    case Employed = 'employed';
    case Unemployed = 'unemployed';
    case OFW = 'ofw';
    case Indigenous = 'indigenous';
    case Student = 'student';
    case OSC = 'osc';
    case OSY = 'osy';
    case Migrant = 'migrant';
    case Refugee = 'refugee';
    case SeniorCitizen = 'senior_citizen';
    case PWD = 'pwd';
    case SoloParent = 'solo_parent';

    public function label(): string
    {
        return match ($this) {
            self::Employed => 'Employed',
            self::Unemployed => 'Unemployed',
            self::OFW => 'Overseas Filipino Worker (OFW)',
            self::Indigenous => 'Indigenous People (IP)',
            self::Student => 'Student',
            self::OSC => 'Out of School Children (OSC)',
            self::OSY => 'Out of School Youth (OSY)',
            self::Migrant => 'Migrant',
            self::Refugee => 'Refugee',
            self::SeniorCitizen => 'Senior Citizen (SC)',
            self::PWD => 'Persons with Disability (PWD)',
            self::SoloParent => 'Single/Solo Parent',
        };
    }
}