<?php

namespace App\Http\Requests\ChildNutrition;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChildNutritionAssessmentRequest extends FormRequest
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
            'assessment_date' => [
                'sometimes',
                'required',
                'date',
            ],

            'age_months' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
            ],

            'weight' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:999.99',
            ],

            'height' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:999.99',
            ],

            'muac' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:99.99',
            ],

            'edema' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            'weight_for_age' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'height_for_age' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'weight_for_height' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'muac_edema_status' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'remarks' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ];
    }
}
