<?php

namespace App\Http\Requests\Ethnicity;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEthnicityRequest extends FormRequest
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
        $ethnicity = $this->route('ethnicity');

        return [
            'ethnicity' => [

                'required',

                'string',

                'max:150',

                Rule::unique('ethnicities')
                    ->ignore($ethnicity),

            ],

            'is_active' => [

                'boolean',

            ],
        ];
    }
}
