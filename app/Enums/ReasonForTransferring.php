<?php

namespace App\Enums;

use App\Traits\HasOptions;

enum ReasonForTransferring: string
{
    use HasOptions;

    case AvailabilityOfJobs = 'availability_of_jobs';
    case HigherWage = 'higher_wage';
    case PresenceOfSchools = 'presence_of_schools';
    case RelativesAndFriends = 'relatives_and_friends';
    case Others = 'others';

    public function label(): string
    {
        return match ($this) {
            self::AvailabilityOfJobs => 'Availability of jobs',
            self::HigherWage => 'Higher wage',
            self::PresenceOfSchools => 'Presence of schools or universities',
            self::RelativesAndFriends => 'Presence of relatives & friends in other place',
            self::Others => 'Others',
        };
    }

    public function code(): string
    {
        return match ($this) {
            self::AvailabilityOfJobs => '1',
            self::HigherWage => '2',
            self::PresenceOfSchools => '3',
            self::RelativesAndFriends => '4',
            self::Others => '5',
        };
    }
}