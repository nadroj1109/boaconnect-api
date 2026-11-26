<?php

namespace App\Http\Requests\DocumentTypeRequirement;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentTypeRequirementRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'is_required' => [
                'required',
                'boolean',
            ],

            'allowed_file_types' => [
                'nullable',
                'string',
                'max:255',
            ],

            'max_file_size' => [
                'nullable',
                'integer',
                'min:1',
                'max:51200',
            ],

            'sort_order' => [
                'required',
                'integer',
                'min:0',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' =>
                'A requirement name is required.',

            'max_file_size.integer' =>
                'The maximum file size must be a valid number.',

            'max_file_size.min' =>
                'The maximum file size must be at least 1 KB.',

            'max_file_size.max' =>
                'The maximum file size cannot exceed 50 MB.',

            'sort_order.required' =>
                'Please specify the requirement order.',
        ];
    }
}
