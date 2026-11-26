<?php

namespace App\Http\Requests\Complaint;

use App\Enums\ComplaintStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHistoricalComplaintRequest extends FormRequest
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
                'required',
                'integer',
                'exists:residents,id',
            ],

            'respondent_id' => [
                'nullable',
                'integer',
                'exists:residents,id',
            ],

            'complaint_no' => [
                'nullable',
                'string',
                'max:100',
                'unique:complaints,complaint_no',
            ],

            'complaint_type_id' => [
                'required',
                'integer',
                Rule::exists('complaint_types', 'id')
                    ->where('is_active', true),
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

            'status' => [
                'required',
                Rule::enum(ComplaintStatus::class),
            ],

            'assigned_to' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'resolution' => [
                'nullable',
                'string',
            ],

            'resolved_at' => [
                'nullable',
                'date',
            ],

            'closed_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}
