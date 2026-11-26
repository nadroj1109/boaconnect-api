<?php

namespace App\Http\Resources\Household;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HouseholdResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            /*
            |--------------------------------------------------------------------------
            | Address
            |--------------------------------------------------------------------------
            */

            'region_id' => $this->region_id,
            'province_id' => $this->province_id,
            'city_id' => $this->city_id,
            'barangay_id' => $this->barangay_id,

            'region' => $this->region?->region,
            'province' => $this->province?->province,
            'city' => $this->city?->city,
            'barangay' => $this->barangay?->barangay,

            'zip_code' => $this->zip_code,

            'sitio_purok_id' => $this->sitio_purok_id,
            'sitio_purok' => $this->sitioPurok?->sitio_purok ?? 'n/a',

            'house_no' => $this->house_no,
            'street_name' => $this->street_name,
            'subdivision' => $this->subdivision,

            /*
             * Keep this temporarily for backward compatibility.
             * The value is now generated from the structured address.
             */
            'complete_address' => collect([
                $this->house_no,
                $this->street_name,
                $this->subdivision,
                $this->sitioPurok?->sitio_purok,
                $this->barangay?->barangay,
                $this->city?->city,
                $this->province?->province,
                $this->region?->region,
                $this->zip_code,
            ])
                ->filter(
                    fn ($value) =>
                        $value !== null &&
                        $value !== ''
                )
                ->implode(', '),

            /*
            |--------------------------------------------------------------------------
            | Household Information
            |--------------------------------------------------------------------------
            */

            'no_of_family' => $this->no_of_family,
            'no_of_hh_member' => $this->no_of_hh_member,
            'no_of_migrant' => $this->no_of_migrant,

            'household_no' => $this->household_no,

            'household_type' =>
                $this->household_type?->value,

            'household_type_other' =>
                $this->household_type_other,

            'tenure_status' =>
                $this->tenure_status,

            'tenure_status_other' =>
                $this->tenure_status_other,

            'household_unit' =>
                $this->household_unit?->value,

            'household_unit_other' =>
                $this->household_unit_other,

            'household_name' =>
                $this->household_name ?? 'n/a',

            'monthly_income' =>
                $this->monthly_income,

            'has_household_head' =>
                $this->residents()
                    ->where('is_household_head', true)
                    ->exists(),
        ];
    }
}
