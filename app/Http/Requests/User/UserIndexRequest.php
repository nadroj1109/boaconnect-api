<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Http\Requests\BaseIndexRequest;

class UserIndexRequest extends BaseIndexRequest
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
        return [
            ...parent::rules(),

            'role_id' => [
                'nullable',
                'integer',
                Rule::exists('roles', 'id'),
            ],
        ];
    }

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
        return 'created_at';
    }

    protected function sortableColumns(): array
    {
        return [

            'email',

            'created_at',

            'is_active',

        ];
    }
}
