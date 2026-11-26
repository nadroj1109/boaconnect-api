<?php

namespace App\Http\Requests\DocumentRequest;

use App\Enums\DocumentDeliveryMethod;

use App\Models\DocumentType;
use App\Models\Resident;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWalkInDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'requester_id' => [
                'required',
                'integer',
                Rule::exists(
                    Resident::class,
                    'id'
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

            'barangay_id' => [
                'required',
                'integer',
                Rule::exists(
                    'barangays',
                    'id'
                ),
            ],

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

            'reference_no' => [
                'nullable',
                'string',
                'max:100',
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

            'requester_id.required' =>
                'Please select the resident making the request.',

            'requester_id.exists' =>
                'The selected requester could not be found.',

            'requested_for_resident_id.required' =>
                'Please select the resident for whom the document is being requested.',

            'requested_for_resident_id.exists' =>
                'The selected resident could not be found.',

            'barangay_id.required' =>
                'Please select a barangay.',

            'barangay_id.exists' =>
                'The selected barangay could not be found.',

            'document_type_id.required' =>
                'Please select a document type.',

            'document_type_id.exists' =>
                'The selected document type is not available.',

            'purpose.required' =>
                'Please provide the purpose of the document request.',

            'delivery_method.required' =>
                'Please select a delivery method.',

            'delivery_method.enum' =>
                'The selected delivery method is invalid.',

            'reference_no.max' =>
                'The payment reference number may not exceed 100 characters.',
        ];
    }
}
