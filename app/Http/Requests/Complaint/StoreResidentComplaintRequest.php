<?php

namespace App\Http\Requests\Complaint;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResidentComplaintRequest extends FormRequest
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
            'respondent_id' => [
                'required',
                'integer',
                Rule::exists(
                    'residents',
                    'id'
                )->where(
                    'barangay_id',
                    $this->user()?->resident?->barangay_id
                ),
            ],
            
            'complaint_type_id' => [
                'required',
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
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'required',
                'string',
            ],

            'incident_date' => [
                'required',
                'date',
            ],

            'incident_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'incident_location' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
