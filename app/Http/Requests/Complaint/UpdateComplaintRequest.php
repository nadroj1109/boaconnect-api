<?php

namespace App\Http\Requests\Complaint;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplaintRequest extends FormRequest
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
            'resident_id' => [
                'sometimes',
                'integer',
                'exists:residents,id',
            ],

            'respondent_id' => [
                'sometimes',
                'integer',
                'exists:residents,id',
            ],

            'complaint_type_id' => [
                'sometimes',
                'integer',
                Rule::exists(
                    'complaint_types',
                    'id'
                )->where(
                    'is_active',
                    true
                ),
            ],

            'subject' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'description' => [
                'sometimes',
                'string',
            ],

            'incident_date' => [
                'sometimes',
                'date',
            ],

            'incident_time' => [
                'nullable',
                // 'date_format:H:i',
            ],

            'incident_location' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
