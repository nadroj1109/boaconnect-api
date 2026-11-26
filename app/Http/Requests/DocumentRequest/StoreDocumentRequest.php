<?php

namespace App\Http\Requests\DocumentRequest;

use App\Enums\DocumentDeliveryMethod;

use App\Models\DocumentType;
use App\Models\Resident;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->resident !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_type_id' => [
                'required',
                'integer',
                Rule::exists(
                    DocumentType::class,
                    'id'
                )->where(
                    fn ($query) =>
                        $query->where('is_active', true)
                ),
            ],

            'requested_for_resident_id' => [
                'required',
                'integer',
                Rule::exists(
                    Resident::class,
                    'id'
                ),
            ],

            'purpose' => [
                'required',
                'string',
                'max:1000',
            ],

            'delivery_method' => [
                'required',
                Rule::enum(
                    DocumentDeliveryMethod::class
                ),
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'fields' => [
                'nullable',
                'array',
            ],

            'attachments' => [
                'nullable',
                'array',
            ],

            'attachments.*' => [
                'file',
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
            'document_type_id.required' =>
                'Please select a document type.',

            'document_type_id.exists' =>
                'The selected document type is not available.',

            'requested_for_resident_id.required' =>
                'Please select the resident for whom the document is being requested.',

            'requested_for_resident_id.exists' =>
                'The selected resident could not be found.',

            'purpose.required' =>
                'Please provide the purpose of the document request.',

            'delivery_method.required' =>
                'Please select a delivery method.',

            'delivery_method.enum' =>
                'The selected delivery method is invalid.',
        ];
    }
}
