<?php

namespace App\Http\Requests\ChildNutrition;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChildNutritionMonitoringRequest extends FormRequest
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
            'sequence_no' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
            ],

            'caregiver_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'birthweight' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:99.99',
            ],

            'low_birth_weight' => [
                'sometimes',
                'nullable',
                'boolean',
            ],
        ];
    }
}
