<?php

namespace App\Http\Requests\Resident;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResidentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [

            'is_household_head' => [
                'required',
                'boolean',
            ],

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
                Rule::requiredIf(
                    $this->gender === 'others'
                ),
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
                'exists:regions,id',
            ],

            'province_id' => [
                'required',
                'exists:provinces,id',
            ],

            'city_id' => [
                'required',
                'exists:cities,id',
            ],

            'barangay_id' => [
                'required',
                'exists:barangays,id',
            ],

            'sitio_purok' => [
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
                Rule::requiredIf(
                    $this->religion === 'others'
                ),
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
                Rule::requiredIf(
                    $this->govt_asst_progam === 'others'
                ),
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

            /* Household Information */

            'relationship_to_hh_head' => [
                'required',
            ],

            'source_of_income' => [
                'nullable',
                'string',
            ],

            'source_of_income_other' => [
                Rule::requiredIf(
                    $this->source_of_income === 'others'
                ),
                'nullable',
                'string',
                'max:100',
            ],

            'monthly_income' => [
                'nullable',
                'numeric',
                'min:0',
            ],

        ];
    }
}
