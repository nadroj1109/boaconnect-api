<?php

namespace App\Enums;

use App\Traits\HasOptions;

enum ReasonForLeaving: string
{
    use HasOptions;

    case LackOfEmployment = 'lack_of_employment';
    case BetterIncome = 'better_income';
    case Schooling = 'schooling';
    case RelativesAndFriends = 'relatives_and_friends';
    case JobRelocation = 'job_relocation';
    case DisasterRelatedRelocation = 'disaster_related_relocation';
    case Retirement = 'retirement';
    case LiveWithParents = 'live_with_parents';
    case LiveWithChildren = 'live_with_children';
    case Marriage = 'marriage';
    case AnnulmentDivorceSeparation = 'annulment_divorce_separation';
    case CommutingRelatedReasons = 'commuting_related_reasons';
    case HealthRelatedReasons = 'health_related_reasons';
    case PeaceAndSecurity = 'peace_and_security';
    case ClimateInducedDisplacement = 'climate_induced_displacement';
    case Others = 'others';

    public function label(): string
    {
        return match ($this) {
            self::LackOfEmployment => 'Lack of employment',
            self::BetterIncome => 'Perception of better income in other place',
            self::Schooling => 'Schooling',
            self::RelativesAndFriends => 'Presence of relatives and friends in other place',
            self::JobRelocation => 'Employment/Job relocation',
            self::DisasterRelatedRelocation => 'Disaster-related relocation',
            self::Retirement => 'Retirement',
            self::LiveWithParents => 'To live with parents',
            self::LiveWithChildren => 'To live with children',
            self::Marriage => 'Marriage',
            self::AnnulmentDivorceSeparation => 'Annulment/Divorce/Separation',
            self::CommutingRelatedReasons => 'Commuting-related reasons',
            self::HealthRelatedReasons => 'Health-related reasons',
            self::PeaceAndSecurity => 'Peace and security',
            self::ClimateInducedDisplacement => 'Climate-induced displacement',
            self::Others => 'Others',
        };
    }

    public function code(): string
    {
        return match ($this) {
            self::LackOfEmployment => '1',
            self::BetterIncome => '2',
            self::Schooling => '3',
            self::RelativesAndFriends => '4',
            self::JobRelocation => '5',
            self::DisasterRelatedRelocation => '6',
            self::Retirement => '7',
            self::LiveWithParents => '8',
            self::LiveWithChildren => '9',
            self::Marriage => '10',
            self::AnnulmentDivorceSeparation => '11',
            self::CommutingRelatedReasons => '12',
            self::HealthRelatedReasons => '13',
            self::PeaceAndSecurity => '14',
            self::ClimateInducedDisplacement => '15',
            self::Others => '16',
        };
    }
}