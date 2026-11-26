<?php

namespace App\Http\Requests\ComplaintType;

// use Illuminate\Contracts\Validation\ValidationRule;
// use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\BaseIndexRequest;

class ComplaintTypeIndexRequest extends BaseIndexRequest
{
    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function sortableColumns(): array
    {
        return [
            'name',
        ];
    }
}
