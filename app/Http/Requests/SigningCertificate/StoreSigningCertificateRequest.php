<?php

namespace App\Http\Requests\SigningCertificate;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSigningCertificateRequest extends FormRequest
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
            'certificate' => [
                'required',
                'file',
                'extensions:cer,crt',
                'max:2048',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'certificate.required' =>
                'Please upload your PNPKI certificate.',

            'certificate.extensions' =>
                'Only .cer and .crt certificate files are allowed.',

            'certificate.max' =>
                'The certificate file must not exceed 2 MB.',
        ];
    }
}
