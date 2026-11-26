<?php

namespace App\Http\Requests\DocumentTemplate;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentTemplateRequest extends FormRequest
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
                'unique:document_templates,name',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'content' => [
                'required',
                'string',
            ],

            'page_size' => [
                'required',
                'string',
                Rule::in([
                    'A4',
                    'Letter',
                    'Legal',
                ]),
            ],

            'orientation' => [
                'required',
                'string',
                Rule::in([
                    'portrait',
                    'landscape',
                ]),
            ],

            'is_active' => [
                'boolean',
            ],
        ];
    }
}
