<?php

namespace App\Http\Requests\Profile;

use App\Constants\Roles;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSignatureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // return $this->user() !== null;
        return $this->user()?->hasRole(
            Roles::CHAIRPERSON
        ) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'signature' => [
                'required',
                'image',
                'mimes:png',
                'max:2048',
            ],
        ];
    }
}
