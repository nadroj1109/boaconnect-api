<?php

namespace App\Http\Requests\BarangayOfficial;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBarangayOfficialRequest extends FormRequest
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
            'barangay_id' => [
                'required',
                'integer',
                'exists:barangays,id',
            ],

            'resident_id' => [
                'required',
                'integer',
                Rule::exists('residents', 'id')
                    ->where(
                        fn ($query) =>
                            $query->where(
                                'barangay_id',
                                $this->barangay_id
                            )
                    ),
            ],

            'official_position_id' => [
                'required',
                'integer',
                'exists:official_positions,id',
            ],

            'term_start' => [
                'required',
                'date',
            ],

            'term_end' => [
                'nullable',
                'date',
                'after_or_equal:term_start',
            ],

            'is_active' => [
                'boolean',
            ],
        ];
    }
}
