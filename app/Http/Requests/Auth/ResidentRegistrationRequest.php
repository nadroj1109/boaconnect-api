<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use App\Enums\HouseholdType;
use App\Enums\HouseholdUnit;
use App\Enums\HouseholdRelationship;
use App\Enums\TenureStatus;
use App\Models\SitioPurok;

class ResidentRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isHouseholdHead = $this->input('relationship_to_hh_head') === HouseholdRelationship::HouseholdHead->value;

        $hasActiveSitioPuroks = $this->filled('barangay_id')
            && SitioPurok::where('barangay_id', $this->input('barangay_id'))
                ->where('is_active', true)
                ->exists();

        return [

            /*
            |--------------------------------------------------------------------------
            | Account
            |--------------------------------------------------------------------------
            */

            'verification_token' => [
                'required',
                'uuid',
            ],

            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
            ],

            /*
            |--------------------------------------------------------------------------
            | Inhabitant Information
            |--------------------------------------------------------------------------
            */

            'type_of_resident' => [
                'required',
            ],

            /*
            |--------------------------------------------------------------------------
            | Personal Information
            |--------------------------------------------------------------------------
            */

            'philsys_card_no' => [
                'nullable',
                'string',
                'max:25',
            ],

            'last_name' => [
                'required',
                'string',
                'max:100',
            ],

            'first_name' => [
                'required',
                'string',
                'max:100',
            ],

            'middle_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'ext_name' => [
                'nullable',
                'string',
                'max:20',
            ],

            'date_of_birth' => [
                'required',
                'date',
            ],

            'place_of_birth' => [
                'required',
                'string',
                'max:255',
            ],

            'res_mother_upon_birth' => [
                'nullable',
                'string',
                'max:255',
            ],

            'sex' => [
                'required',
            ],

            'gender' => [
                'nullable',
            ],

            'gender_other' => [
                Rule::requiredIf($this->gender === 'others'),
                'nullable',
                'string',
                'max:100',
            ],

            'civil_status' => [
                'required',
            ],

            'pregnant_woman' => [
                'required',
                'boolean',
            ],

            'highest_educ_attainment' => [
                'required',
            ],

            'profession_occupation' => [
                'nullable',
                'string',
                'max:150',
            ],

            /*
            |--------------------------------------------------------------------------
            | Contact
            |--------------------------------------------------------------------------
            */

            'email_address' => [
                'nullable',
                'email',
                'max:255',
            ],

            'mobile_no' => [
                'nullable',
                'string',
                'max:20',
            ],

            'tel_no' => [
                'nullable',
                'string',
                'max:20',
            ],

            /*
            |--------------------------------------------------------------------------
            | Address
            |--------------------------------------------------------------------------
            */

            'region_id' => [
                'required',
                'integer',
                Rule::exists('regions', 'id'),
            ],

            'province_id' => [
                'required',
                'integer',
                Rule::exists('provinces', 'id')
                    ->where('region_id', $this->input('region_id')),
            ],

            'city_id' => [
                'required',
                'integer',
                Rule::exists('cities', 'id')
                    ->where('province_id', $this->input('province_id')),
            ],

            'barangay_id' => [
                'required',
                'integer',
                Rule::exists('barangays', 'id')
                    ->where('city_id', $this->input('city_id')),
            ],

            'sitio_purok_id' => [
                Rule::requiredIf($hasActiveSitioPuroks),
                'nullable',
                'integer',
                Rule::exists('sitio_puroks', 'id')
                    ->where('barangay_id', $this->input('barangay_id'))
                    ->where('is_active', true),
            ],

            'sitio_purok' => [
                Rule::requiredIf(! $hasActiveSitioPuroks),
                'nullable',
                'string',
                'max:150',
            ],

            'house_no' => [
                'nullable',
                'string',
                'max:100',
            ],

            'street_name' => [
                'nullable',
                'string',
                'max:150',
            ],

            'subdivision' => [
                'nullable',
                'string',
                'max:150',
            ],

            'zip_code' => [
                'nullable',
                'string',
                'max:10',
            ],

            /*
            |--------------------------------------------------------------------------
            | Identity
            |--------------------------------------------------------------------------
            */

            'blood_type' => [
                'required',
            ],

            'height' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'weight' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'complexion' => [
                'nullable',
            ],

            'nationality' => [
                'required',
            ],

            'registered_voter' => [
                'required',
                'boolean',
            ],

            'resident_voter' => [
                'required',
                'boolean',
            ],

            'last_voted_yr' => [
                'nullable',
                'integer',
                'min:1900',
                'max:' . now()->year,
            ],

            'ethnicity_id' => [
                'nullable',
                'exists:ethnicities,id',
            ],

            'religion' => [
                'required',
            ],

            'religion_other' => [
                Rule::requiredIf($this->religion === 'others'),
                'nullable',
                'string',
                'max:100',
            ],

            /*
            |--------------------------------------------------------------------------
            | Mother's Maiden Name
            |--------------------------------------------------------------------------
            */

            'mother_fname' => [
                'nullable',
                'string',
                'max:100',
            ],

            'mother_midname' => [
                'nullable',
                'string',
                'max:100',
            ],

            'mother_lname' => [
                'nullable',
                'string',
                'max:100',
            ],

            /*
            |--------------------------------------------------------------------------
            | Beneficiary
            |--------------------------------------------------------------------------
            */

            'govt_asst_progam' => [
                'nullable',
            ],

            'govt_asst_progam_other' => [
                Rule::requiredIf($this->govt_asst_progam === 'others'),
                'nullable',
                'string',
                'max:150',
            ],

            /*
            |--------------------------------------------------------------------------
            | Sectors
            |--------------------------------------------------------------------------
            */

            'sectors' => [
                'nullable',
                'array',
            ],

            'sectors.*.sector_id' => [
                'required',
                'exists:sectors,id',
            ],

            'sectors.*.registered' => [
                'nullable',
                'boolean',
            ],

            /*
            |--------------------------------------------------------------------------
            | Household Information (Self-Reported)
            |--------------------------------------------------------------------------
            */

            'relationship_to_hh_head' => [
                'required',
                Rule::enum(HouseholdRelationship::class),
            ],

            'source_of_income' => [
                'nullable',
                'string',
            ],

            'source_of_income_other' => [
                Rule::requiredIf($this->source_of_income === 'others'),
                'nullable',
                'string',
                'max:100',
            ],

            'monthly_income' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            /*
            |--------------------------------------------------------------------------
            | Household Registration
            |--------------------------------------------------------------------------
            */

            'requested_household_id' => [
                'prohibited',
            ],

            'household_details' => [
                Rule::requiredIf($isHouseholdHead),
                Rule::prohibitedIf(! $isHouseholdHead),
                'nullable',
                'array',
            ],

            'household_details.no_of_family' => [
                'required_if:relationship_to_hh_head,household_head',
                'nullable',
                'integer',
                'min:1',
            ],

            'household_details.no_of_hh_member' => [
                'required_if:relationship_to_hh_head,household_head',
                'nullable',
                'integer',
                'min:1',
            ],

            'household_details.no_of_migrant' => [
                'required_if:relationship_to_hh_head,household_head',
                'nullable',
                'integer',
                'min:0',
            ],

            'household_details.household_type' => [
                'required_if:relationship_to_hh_head,household_head',
                'nullable',
                Rule::enum(HouseholdType::class),
            ],

            'household_details.household_type_other' => [
                Rule::requiredIf(
                    $this->input('household_details.household_type') === HouseholdType::Others->value
                ),
                'nullable',
                'string',
                'max:100',
            ],

            'household_details.tenure_status' => [
                'required_if:relationship_to_hh_head,household_head',
                'nullable',
                Rule::enum(TenureStatus::class),
            ],

            'household_details.tenure_status_other' => [
                Rule::requiredIf(
                    $this->input('household_details.tenure_status') === TenureStatus::Others->value
                ),
                'nullable',
                'string',
                'max:50',
            ],

            'household_details.household_unit' => [
                'required_if:relationship_to_hh_head,household_head',
                'nullable',
                Rule::enum(HouseholdUnit::class),
            ],

            'household_details.household_unit_other' => [
                Rule::requiredIf(
                    $this->input('household_details.household_unit') === HouseholdUnit::Others->value
                ),
                'nullable',
                'string',
                'max:50',
            ],

            'household_details.household_name' => [
                'nullable',
                'string',
                'max:150',
            ],

            'household_details.monthly_income' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'requested_household_no' => [
                Rule::requiredIf(! $isHouseholdHead),
                Rule::prohibitedIf($isHouseholdHead),
                'nullable',
                'string',
                'max:50',
                Rule::exists('households', 'household_no')
                    ->where('barangay_id', $this->input('barangay_id')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'requested_household_no.required' =>
                'Please enter your household number.',

            'requested_household_no.exists' =>
                'The household number was not found in the selected barangay.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            if (
                $this->sex === 'male' &&
                $this->boolean('pregnant_woman')
            ) {
                $validator->errors()->add(
                    'pregnant_woman',
                    'Male residents cannot be marked as pregnant.'
                );
            }

        });
    }
}
