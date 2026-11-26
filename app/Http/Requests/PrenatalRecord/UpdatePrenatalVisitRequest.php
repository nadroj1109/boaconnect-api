<?php

namespace App\Http\Requests\PrenatalRecord;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrenatalVisitRequest extends FormRequest
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
                'sometimes',
                'nullable',
                'integer',
                'between:1,8',
                Rule::unique('prenatal_visits', 'visit_number')
                    ->where('prenatal_record_id', $this->route('prenatalRecord'))
                    ->ignore($this->route('visit')),
            ],
            'visit_date' => ['sometimes', 'required', 'date'],
            'aog_weeks' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:45'],
            'aog_days' => ['sometimes', 'nullable', 'integer', 'between:0,6'],
            'blood_pressure' => ['sometimes', 'nullable', 'string', 'max:50'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'fundal_height' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'fetal_heart_beat' => ['sometimes', 'nullable', 'string', 'max:100'],
            'presentation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'medicine_given' => ['sometimes', 'nullable', 'string'],
            'danger_signs' => ['sometimes', 'nullable', 'string'],
            'hbsag' => ['sometimes', 'nullable', 'string', 'max:255'],
            'hiv_screening' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gestational_diabetes' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cbc_hgb_hct' => ['sometimes', 'nullable', 'string'],
            'rpr_rdt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remarks' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
