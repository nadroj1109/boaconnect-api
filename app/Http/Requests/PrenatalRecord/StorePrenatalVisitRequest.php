<?php

namespace App\Http\Requests\PrenatalRecord;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrenatalVisitRequest extends FormRequest
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
            'visit_number' => [
                'nullable',
                'integer',
                'between:1,8',
                Rule::unique('prenatal_visits', 'visit_number')
                    ->where('prenatal_record_id', $this->route('prenatalRecord')),
            ],
            'visit_date' => ['required', 'date'],
            'aog_weeks' => ['nullable', 'integer', 'min:0', 'max:45'],
            'aog_days' => ['nullable', 'integer', 'between:0,6'],
            'blood_pressure' => ['nullable', 'string', 'max:50'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'fundal_height' => ['nullable', 'numeric', 'min:0'],
            'fetal_heart_beat' => ['nullable', 'string', 'max:100'],
            'presentation' => ['nullable', 'string', 'max:255'],
            'medicine_given' => ['nullable', 'string'],
            'danger_signs' => ['nullable', 'string'],
            'hbsag' => ['nullable', 'string', 'max:255'],
            'hiv_screening' => ['nullable', 'string', 'max:255'],
            'gestational_diabetes' => ['nullable', 'string', 'max:255'],
            'cbc_hgb_hct' => ['nullable', 'string'],
            'rpr_rdt' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
