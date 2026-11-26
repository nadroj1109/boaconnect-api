<?php

namespace App\Enums;

use App\Traits\HasOptions;

enum HighestEducationalAttainment: string
{
    use HasOptions;

    case NoEducation = 'no_education';
    case Preschool = 'pre_school';
    case ElementaryLevel = 'elementary_level';
    case ElementaryGraduate = 'elementary_graduate';
    case HighSchoolLevel = 'high_school_level';
    case HighSchoolGraduate = 'high_school_graduate';
    case JuniorHS = 'junior_hs';
    case JuniorHSGraduate = 'junior_hs_graduate';
    case SeniorHSLevel = 'senior_hs_level';
    case SeniorHSGraduate = 'senior_hs_graduate';
    case VocationalTech = 'vocational_tech';
    case CollegeLevel = 'college_level';
    case CollegeGraduate = 'college_graduate';
    case PostGraduate = 'post_graduate';

    public function label(): string
    {
        return match ($this) {
            self::NoEducation => 'No Education',
            self::Preschool => 'Pre-school',
            self::ElementaryLevel => 'Elementary Level',
            self::ElementaryGraduate => 'Elementary Graduate',
            self::HighSchoolLevel => 'High School Level',
            self::HighSchoolGraduate => 'High School Graduate',
            self::JuniorHS => 'Junior HS',
            self::JuniorHSGraduate => 'Junior HS Graduate',
            self::SeniorHSLevel => 'Senior HS Level',
            self::SeniorHSGraduate => 'Senior HS Graduate',
            self::VocationalTech => 'Vocational/Tech',
            self::CollegeLevel => 'College Level',
            self::CollegeGraduate => 'College Graduate',
            self::PostGraduate => 'Post-graduate',
        };
    }
}