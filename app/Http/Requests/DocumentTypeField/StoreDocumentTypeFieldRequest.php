<?php

namespace App\Http\Requests\DocumentTypeField;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentTypeFieldRequest extends FormRequest
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
            ],

            'field_key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9_]*$/',
            ],

            'field_type' => [
                'required',
                Rule::in([
                    'text',
                    'textarea',
                    'number',
                    'date',
                    'select',
                    'radio',
                    'checkbox',
                ]),
            ],

            'is_required' => [
                'boolean',
            ],

            'options' => [
                'exclude_if:option_source,barangays',
                'required',
                'array',
                'min:1',
            ],

            'options.*' => [
                'exclude_if:option_source,barangays',
                'required',
                'string',
                'max:255',
            ],

            'option_source' => [
                'nullable',
                Rule::in([
                    'barangays',
                    // additional source can be added if needed
                ]),
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'is_active' => [
                'boolean',
            ],
        ];
    }
}
