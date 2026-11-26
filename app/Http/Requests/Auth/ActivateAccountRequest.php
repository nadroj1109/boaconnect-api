<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ActivateAccountRequest extends FormRequest
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
            'token' => [

                'required',

                'uuid',

            ],

            'password' => [

                'required',

                'confirmed',

                'min:8',

                'max:100',

            ],
        ];
    }

    public function attributes(): array
    {
        return [

            'token' => 'activation token',

        ];
    }
}
