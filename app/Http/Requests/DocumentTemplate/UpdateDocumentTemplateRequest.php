<?php

namespace App\Http\Requests\DocumentTemplate;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentTemplateRequest extends FormRequest
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
        $documentTemplate = $this->route(
            'document_template'
        );

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(
                    'document_templates',
                    'name'
                )->ignore(
                    $documentTemplate
                ),
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
