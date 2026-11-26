<?php

namespace App\Http\Requests\Barangay;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBarangayRequest extends FormRequest
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
        $barangay = $this->route('barangay');

        return [

            // 'municipality_id' => [

            //     'required',

            //     'exists:municipalities,id',

            // ],

            'barangay' => [

                'required',

                'string',

                'max:150',

                Rule::unique('barangays')
                    ->ignore($barangay)
                    ->where(function ($query) {

                        return $query->where(
                            'city_id',
                            $this->city_id
                        );

                    }),

            ],

            'is_active' => [

                'boolean',

            ],

        ];
    }
}
