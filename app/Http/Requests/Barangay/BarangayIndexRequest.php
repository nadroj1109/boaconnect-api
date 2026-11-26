<?php

namespace App\Http\Requests\Barangay;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\BaseIndexRequest;

class BarangayIndexRequest extends BaseIndexRequest
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
    // public function rules(): array
    // {
    //     return [

    //         'search' => ['nullable','string'],

    //         'sort' => ['nullable','string'],

    //         'direction' => ['nullable','in:asc,desc'],

    //         'per_page' => ['nullable','integer'],

    //         'page' => ['nullable','integer'],

    //     ];
    // }

    // public function validated($key = null, $default = null)
    // {
    //     return array_merge(parent::validated(), [

    //         'sort' => $this->input('sort', 'created_at'),

    //         'direction' => $this->input('direction', 'desc'),

    //         'per_page' => $this->input('per_page', 10),

    //     ]);
    // }

    protected function defaultSort(): string
    {
        return 'barangay';
    }

    protected function sortableColumns(): array
    {
        return [

            'barangay',

        ];
    }
}
