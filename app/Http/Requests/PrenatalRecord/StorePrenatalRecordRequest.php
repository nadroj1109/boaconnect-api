<?php

namespace App\Http\Requests\PrenatalRecord;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePrenatalRecordRequest extends FormRequest
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
            'resident_id' => ['required', 'integer', 'exists:residents,id'],
            'registration_date' => ['required', 'date'],
            'spouse_name' => ['nullable', 'string', 'max:255'],
            'ob_score' => ['nullable', 'string', 'max:100'],
            'lmp' => ['nullable', 'date'],
            'edc' => ['nullable', 'date', 'after_or_equal:lmp'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'initial_weight' => ['nullable', 'numeric', 'min:0'],
            'bmi' => ['nullable', 'numeric', 'min:0'],
            'past_medical_history' => ['nullable', 'string'],
            'family_history' => ['nullable', 'string'],
            'tt1_date' => ['nullable', 'date'],
            'tt2_date' => ['nullable', 'date'],
            'tt3_date' => ['nullable', 'date'],
            'tt4_date' => ['nullable', 'date'],
            'tt5_date' => ['nullable', 'date'],
            'pregnancy_outcome' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
