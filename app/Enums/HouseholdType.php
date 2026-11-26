<?php

namespace App\Enums;

use App\Traits\HasOptions;

enum HouseholdType: string
{
    use HasOptions;

    case NuclearFamily = 'nuclear_family';
    case ExtendedFamily = 'extended_family';
    case SingleParentFamily = 'single_parent_family';
    case ChildlessFamily = 'childless_family';
    case BlendedFamily = 'blended_family';
    case SinglePersonHousehold = 'single_person_household';
    case NonRelatedFamily = 'non_related_family';
    case Others = 'others';

    public function label(): string
    {
        return match ($this) {
            self::NuclearFamily => 'Nuclear Family',
            self::ExtendedFamily => 'Extended Family',
            self::SingleParentFamily => 'Single/Solo Parent Family',
            self::ChildlessFamily => 'Childless Family',
            self::BlendedFamily => 'Blended/Stepfamily',
            self::SinglePersonHousehold => 'Single Person Household',
            self::NonRelatedFamily => 'Non-related Family',
            self::Others => 'Others',
        };
    }
}