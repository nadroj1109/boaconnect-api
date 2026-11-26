<?php

namespace App\Http\Requests\Household;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHouseholdRequest extends FormRequest
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
        $household = $this->route('household');

        return [
            /*
            |--------------------------------------------------------------------------
            | Part 1
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

            'zip_code' => [
                'nullable',
                'string',
                'max:10',
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

            'sitio_purok_id' => [
                'required',
                'exists:sitio_puroks,id',
            ],
            
            // 'complete_address' => [
            //     'required',
            //     'string',
            //     'max:255',
            // ],

            'no_of_family' => [
                'required',
                'integer',
                'min:1',
            ],

            'no_of_hh_member' => [
                'required',
                'integer',
                'min:1',
            ],

            'no_of_migrant' => [
                'required',
                'integer',
                'min:0',
            ],

            /*
            |--------------------------------------------------------------------------
            | Part 2
            |--------------------------------------------------------------------------
            */

            'household_type' => [
                'required',
                'string',
                'max:100',
            ],

            'household_type_other' => [
                'nullable',
                'required_if:household_type,others',
                'string',
                'max:150',
            ],

            'tenure_status' => [
                'required',
                'string',
                'max:100',
            ],

            'tenure_status_other' => [
                'nullable',
                'required_if:tenure_status,others',
                'string',
                'max:150',
            ],

            'household_unit' => [
                'required',
                'string',
                'max:100',
            ],

            'household_unit_other' => [
                'nullable',
                'required_if:household_unit,others',
                'string',
                'max:150',
            ],

            'household_no' => [
                'required',
                // 'string',
                'max:150',
                Rule::unique('households')
                    ->ignore($household),
            ],

            'household_name' => [
                'nullable',
                'string',
                'max:150',
            ],

            'monthly_income' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ];
    }
}
