<?php

namespace App\Http\Requests\Resident;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\BaseIndexRequest;

class ResidentIndexRequest extends BaseIndexRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    // public function authorize(): bool
    // {
    //     return true;
    // }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [

            'household_id' => [
                'nullable',
                'integer',
                'exists:households,id',
            ],

        ]);
    }

    protected function defaultSort(): string
    {
        return 'last_name';
    }

    protected function sortableColumns(): array
    {
        return [
            'last_name',
            'first_name',
        ];
    }
}
