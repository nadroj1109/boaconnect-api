<?php

namespace App\Http\Requests\Resident;

use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResidentHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $resident = $this->route('resident');

        $registration = $resident instanceof Resident
            ? $resident->householdRegistration
            : null;

        $isHouseholdHead = $registration
            && $registration->requested_household_id === null;

        return [
            'household_no' => [
                Rule::requiredIf($isHouseholdHead),
                Rule::prohibitedIf(!$isHouseholdHead),
                'string',
                'max:50',
                'unique:households,household_no',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'household_no.required' =>
                'Please enter the household number for this new household.',

            'household_no.unique' =>
                'This household number is already assigned to another household.',
        ];
    }
}
