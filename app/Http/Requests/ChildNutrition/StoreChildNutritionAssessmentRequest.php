<?php

namespace App\Http\Requests\ChildNutrition;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreChildNutritionAssessmentRequest extends FormRequest
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
            'assessment_month' => [
                'required',
                'integer',
                'between:1,12',
            ],

            'assessment_date' => [
                'required',
                'date',
            ],

            'age_months' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'weight' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999.99',
            ],

            'height' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999.99',
            ],

            'muac' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99.99',
            ],

            'edema' => [
                'nullable',
                'string',
                'max:50',
            ],

            'weight_for_age' => [
                'nullable',
                'string',
                'max:100',
            ],

            'height_for_age' => [
                'nullable',
                'string',
                'max:100',
            ],

            'weight_for_height' => [
                'nullable',
                'string',
                'max:100',
            ],

            'muac_edema_status' => [
                'nullable',
                'string',
                'max:100',
            ],

            'remarks' => [
                'nullable',
                'string',
            ],
        ];
    }
}
