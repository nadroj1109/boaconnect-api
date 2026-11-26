<?php

namespace App\Http\Requests\Complaint;

use App\Enums\ComplaintHearingStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHistoricalComplaintHearingRequest extends FormRequest
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
            'hearing_date' => [
                'required',
                'date',
            ],

            'hearing_time' => [
                'required',
                'date_format:H:i',
            ],

            'location' => [
                'required',
                'string',
                'max:255',
            ],

            'status' => [
                'required',
                Rule::enum(
                    ComplaintHearingStatus::class
                ),
            ],

            'remarks' => [
                'nullable',
                'string',
            ],
        ];
    }
}
