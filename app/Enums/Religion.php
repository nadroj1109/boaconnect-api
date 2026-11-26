<?php

namespace App\Enums;

use App\Traits\HasOptions;

enum Religion: string
{
    use HasOptions;

	case RomanCatholic = 'roman_catholic';
	case Islam = 'islam';
	case IglesiaNiCristo = 'iglesia_ni_cristo';
	case Christian = 'christian';
	case AglipayanChurch = 'aglipayan_church';
	case SeventhdayAdventist = 'seventhday_adventist';
	case BibleBaptistChurch = 'bible_baptist_church';
	case JehovahsWitnesses = 'jehovahs_witnesses';
	case ChurchofJesusChrist = 'church_of_jesus_christ';
	case UnitedChurchofChrist = 'united_church_of_christ';
	case Others = 'others';

	public function label(): string
    {
        return match ($this) {
            self::RomanCatholic => 'Roman Catholic',
            self::Islam => 'Islam',
            self::IglesiaNiCristo => 'Iglesia ni Cristo',
            self::Christian => 'Christian',
            self::AglipayanChurch => 'Aglipayan Church',
            self::SeventhdayAdventist => 'Seventh-day Adventist',
            self::BibleBaptistChurch => 'Bible Baptist Church',
            self::JehovahsWitnesses => "Jehovah's Witnesses",
            self::ChurchofJesusChrist => 'Church of Jesus Christ of Latter-day Saints',
            self::UnitedChurchofChrist => 'United Church of Christ in the Philippines',
            self::Others => 'Others',
        };
    }
}