<?php

namespace App\Enums;

use App\Traits\HasOptions;

enum HouseholdRelationship: string
{
    use HasOptions;

    case HouseholdHead = 'household_head';
    case Spouse = 'spouse';
    case CommonLawPartner = 'common_law_partner';
    case Son = 'son';
    case Daughter = 'daughter';
    case Stepson = 'stepson';
    case Stepdaughter = 'stepdaughter';
    case SonInLaw = 'son_in_law';
    case DaughterInLaw = 'daughter_in_law';
    case Grandson = 'grandson';
    case Granddaughter = 'granddaughter';
    case Father = 'father';
    case Mother = 'mother';
    case FatherInLaw = 'father_in_law';
    case MotherInLaw = 'mother_in_law';
    case Brother = 'brother';
    case Sister = 'sister';
    case BrotherInLaw = 'brother_in_law';
    case SisterInLaw = 'sister_in_law';
    case Uncle = 'uncle';
    case Aunt = 'aunt';
    case Nephew = 'nephew';
    case Niece = 'niece';
    case OtherRelative = 'other_relative';
    case Boarder = 'boarder';
    case DomesticHelper = 'domestic_helper';
    case OtherNonRelative = 'other_non_relative';

    /**
     * Display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::HouseholdHead => 'Household Head',
            self::Spouse => 'Spouse',
            self::CommonLawPartner => 'Common-law/Live-in Partner',
            self::Son => 'Son',
            self::Daughter => 'Daughter',
            self::Stepson => 'Stepson',
            self::Stepdaughter => 'Stepdaughter',
            self::SonInLaw => 'Son-in-law',
            self::DaughterInLaw => 'Daughter-in-law',
            self::Grandson => 'Grandson',
            self::Granddaughter => 'Granddaughter',
            self::Father => 'Father',
            self::Mother => 'Mother',
            self::FatherInLaw => 'Father-in-Law',
            self::MotherInLaw => 'Mother-in-Law',
            self::Brother => 'Brother',
            self::Sister => 'Sister',
            self::BrotherInLaw => 'Brother-in-Law',
            self::SisterInLaw => 'Sister-in-Law',
            self::Uncle => 'Uncle',
            self::Aunt => 'Aunt',
            self::Nephew => 'Nephew',
            self::Niece => 'Niece',
            self::OtherRelative => 'Other Relative',
            self::Boarder => 'Boarder',
            self::DomesticHelper => 'Domestic Helper',
            self::OtherNonRelative => 'Other Non-relative',
        };
    }

    /**
     * Official DILG/PSA relationship code.
     */
    public function code(): string
    {
        return match ($this) {
            self::HouseholdHead => '1',
            self::Spouse => '2a',
            self::CommonLawPartner => '2b',
            self::Son => '3',
            self::Daughter => '4',
            self::Stepson => '5',
            self::Stepdaughter => '6',
            self::SonInLaw => '7',
            self::DaughterInLaw => '8',
            self::Grandson => '9',
            self::Granddaughter => '10',
            self::Father => '11',
            self::Mother => '12',
            self::FatherInLaw => '13',
            self::MotherInLaw => '14',
            self::Brother => '15',
            self::Sister => '16',
            self::BrotherInLaw => '17',
            self::SisterInLaw => '18',
            self::Uncle => '19',
            self::Aunt => '20',
            self::Nephew => '21',
            self::Niece => '22',
            self::OtherRelative => '23',
            self::Boarder => '24',
            self::DomesticHelper => '25',
            self::OtherNonRelative => '26',
        };
    }
}