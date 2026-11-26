<?php

namespace App\Http\Requests\DocumentType;

use App\Constants\Permissions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
        // return $this->user() !== null
        //     && $this->user()->can(Permissions::DOCUMENT_TYPE_CREATE);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->boolean('requires_payment')) {
            $this->merge([
                'fee' => null,
                'allow_online_payment' => false,
            ]);
        }
    }
    
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 'code' => [
            //     'nullable',
            //     'string',
            //     'max:50',
            //     Rule::unique('document_types', 'code'),
            // ],

            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('document_types', 'name'),
            ],

            'document_title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'document_template_id' => [
                'nullable',
                'integer',
                'exists:document_templates,id',
            ],

            'processing_days' => [
                'required',
                'integer',
                'min:0',
                'max:365',
            ],

            'requires_payment' => [
                'required',
                'boolean',
            ],

            'fee' => [
                Rule::requiredIf(
                    fn () => $this->boolean('requires_payment')
                ),
                'nullable',
                'numeric',
                'min:0',
            ],

            'allow_online_payment' => [
                'required',
                'boolean',
            ],

            'requires_chairperson_signature' => [
                'required',
                'boolean',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' =>
                'The document type code is already in use.',

            'name.required' =>
                'A document type name is required.',

            'name.unique' =>
                'The document type name is already in use.',

            'processing_days.required' =>
                'Please specify the processing time.',

            'fee.required' =>
                'Please specify the document fee when payment is required.',
        ];
    }
}
