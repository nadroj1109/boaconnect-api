<?php

namespace App\Http\Requests\Household;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\BaseIndexRequest;

class HouseholdIndexRequest extends BaseIndexRequest
{

    protected function defaultSort(): string
    {
        return 'household_name';
    }

    protected function sortableColumns(): array
    {
        return [

            'household_name',

        ];
    }
}
