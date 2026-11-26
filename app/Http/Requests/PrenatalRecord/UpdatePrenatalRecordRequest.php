<?php

namespace App\Http\Requests\PrenatalRecord;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePrenatalRecordRequest extends FormRequest
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
            'registration_date' => ['sometimes', 'required', 'date'],
            'spouse_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ob_score' => ['sometimes', 'nullable', 'string', 'max:100'],
            'lmp' => ['sometimes', 'nullable', 'date'],
            'edc' => ['sometimes', 'nullable', 'date'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'initial_weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'bmi' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'past_medical_history' => ['sometimes', 'nullable', 'string'],
            'family_history' => ['sometimes', 'nullable', 'string'],
            'tt1_date' => ['sometimes', 'nullable', 'date'],
            'tt2_date' => ['sometimes', 'nullable', 'date'],
            'tt3_date' => ['sometimes', 'nullable', 'date'],
            'tt4_date' => ['sometimes', 'nullable', 'date'],
            'tt5_date' => ['sometimes', 'nullable', 'date'],
            'pregnancy_outcome' => ['sometimes', 'nullable', 'string', 'max:100'],
            'remarks' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
