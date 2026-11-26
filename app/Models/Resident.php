<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\Sex;
use App\Enums\Gender;
use App\Enums\CivilStatus;
use App\Enums\BloodType;
use App\Enums\HighestEducationalAttainment;
use App\Enums\Complexion;
use App\Enums\Nationality;
use App\Enums\Religion;
use App\Enums\TypeResident;
use App\Enums\GovtAssistinceProgram;
use App\Enums\SourceOfIncome;
use App\Enums\HouseholdRelationship;
use App\Enums\VerificationChannel;
use App\Models\VerificationCode;

class Resident extends Model
{
    protected $fillable = ['household_id', 'is_household_head', 'type_of_resident', 'philsys_card_no', 'last_name', 'first_name', 'middle_name', 'ext_name', 'date_of_birth', 'age', 'place_of_birth', 'res_mother_upon_birth', 'sex', 'gender', 'gender_other', 'civil_status', 'pregnant_woman', 'highest_educ_attainment', 'profession_occupation', 'email_address', 'mobile_no', 'tel_no', 'region_id', 'province_id', 'city_id', 'barangay_id', 'sitio_purok_id', 'sitio_purok', 'house_no', 'street_name', 'subdivision', 'zip_code', 'blood_type', 'height', 'weight', 'complexion', 'nationality', 'registered_voter', 'resident_voter', 'last_voted_yr', 'ethnicity_id', 'religion', 'religion_other', 'mother_fname', 'mother_midname', 'mother_lname', 'govt_asst_progam', 'govt_asst_progam_other', 'relationship_to_hh_head', 'source_of_income', 'source_of_income_other', 'monthly_income'];

    protected function casts(): array
    {
        return [
            'type_of_resident' => TypeResident::class,
            'date_of_birth' => 'date',
            'sex' => Sex::class,
            'gender' => Gender::class,
            'civil_status' => CivilStatus::class,
            'blood_type' => BloodType::class,
            'highest_educ_attainment' => HighestEducationalAttainment::class,
            'complexion' => Complexion::class,
            'nationality' => Nationality::class,
            'religion' => Religion::class,
            'govt_asst_progam' => GovtAssistinceProgram::class,
            'relationship_to_hh_head' => HouseholdRelationship::class,
            'source_of_income' => SourceOfIncome::class,
            'registered_voter' => 'boolean',
            'resident_voter' => 'boolean',
            'pregnant_woman' => 'boolean',
            'is_household_head' => 'boolean',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(

            get: fn () => trim(implode(' ', array_filter([

                $this->first_name,

                $this->middle_name,

                $this->last_name,

                $this->ext_name,

            ])))

        );
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function household()
    {
        return $this->belongsTo(Household::class, 'household_id');
    }

    public function ethnicity()
    {
        return $this->belongsTo(Ethnicity::class, 'ethnicity_id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function province()
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class, 'barangay_id');
    }

    public function sitioPurok()
    {
        return $this->belongsTo(SitioPurok::class, 'sitio_purok_id');
    }

    public function residentSectors()
    {
        return $this->hasMany(ResidentSector::class);
    }

    public function sectors()
    {
        return $this->belongsToMany(
            Sector::class,
            'resident_sectors'
        )
        ->withPivot('registered')
        ->withTimestamps();
    }

    public function barangayOfficials(): HasMany
    {
        return $this->hasMany(
            BarangayOfficial::class
        );
    }

    public function householdRegistration()
    {
        return $this->hasOne(ResidentHouseholdRegistration::class);
    }

    public function prenatalRecords(): HasMany
    {
        return $this->hasMany(PrenatalRecord::class);
    }

    public static function findByVerification(
        VerificationCode $verification,
        array $with = []
    ): ?self {

        $query = static::query()

            ->with($with);

        return match ($verification->channel) {

            VerificationChannel::EMAIL =>

                $query

                    ->where(
                        'email_address',
                        $verification->recipient
                    )

                    ->first(),

            VerificationChannel::SMS =>

                $query

                    ->where(
                        'mobile_no',
                        $verification->recipient
                    )

                    ->first(),

        };

    }

    public function hasAccount(): bool
    {
        return $this->user()->exists();
    }
}
