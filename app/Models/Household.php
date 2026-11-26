<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\HouseholdType;
use App\Enums\HouseholdUnit;
use App\Models\Region;
use App\Models\Province;
use App\Models\City;
use App\Models\Barangay;

class Household extends Model
{
    protected $fillable = [
        'barangay_id',
        'region_id',
        'province_id',
        'city_id',
        'zip_code',
        'sitio_purok_id',
        'sitio_purok',
        'house_no',
        'street_name',
        'subdivision',
        'complete_address',
        'no_of_family',
        'no_of_hh_member',
        'no_of_migrant',
        'household_no',
        'household_type',
        'household_type_other',
        'tenure_status',
        'tenure_status_other',
        'household_unit',
        'household_unit_other',
        'household_name',
        'monthly_income',
    ];

    protected $casts = [
        'household_type' => HouseholdType::class,
        'household_unit' => HouseholdUnit::class,
    ];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function sitioPurok()
    {
        return $this->belongsTo(SitioPurok::class, 'sitio_purok_id');
    }

    public function residents()
    {
        return $this->hasMany(Resident::class, 'household_id');
    }
}
