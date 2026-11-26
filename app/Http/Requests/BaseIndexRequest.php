<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BaseIndexRequest extends FormRequest
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

            'search' => [

                'nullable',

                'string',

                'max:255',

            ],

            'page' => [

                'nullable',

                'integer',

                'min:1',

            ],

            'per_page' => [

                'nullable',

                'integer',

                'min:1',

                'max:'. $this->maxPerPage(),

            ],

            'sort' => [
                'nullable',
                'string',
                Rule::in($this->sortableColumns()),

            ],

            'direction' => [

                'nullable',

                'in:asc,desc',

            ],

        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([

            'search' => $this->search,

            'page' => $this->page ?? 1,

            'per_page' => $this->per_page ?? $this->defaultPerPage(),

            'sort' => $this->sort ?? $this->defaultSort(),

            'direction' => $this->direction ?? 'asc',

        ]);
    }

    /**
     * Default sort column.
     */
    protected function defaultSort(): string
    {
        return 'id';
    }

    /**
     * Columns allowed for sorting.
     * Override this in child classes.
     */
    protected function sortableColumns(): array
    {
        return [

            $this->defaultSort(),

        ];
    }

    protected function defaultPerPage(): int
    {
        return 10;
    }

    protected function maxPerPage(): int
    {
        return 100;
    }
}
