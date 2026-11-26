<?php

namespace App\Http\Requests\ChildNutrition;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreChildNutritionMonitoringRequest extends FormRequest
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

            'monitoring_year' => [
                'required',
                'integer',
                'min:2000',
                'max:2100',
            ],

            'sequence_no' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'caregiver_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'birthweight' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99.99',
            ],

            'low_birth_weight' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}
