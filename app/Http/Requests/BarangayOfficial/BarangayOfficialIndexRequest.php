<?php

namespace App\Http\Requests\BarangayOfficial;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\BaseIndexRequest;

class BarangayOfficialIndexRequest extends BaseIndexRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),

            'barangay_id' => [
                'nullable',
                'integer',
                'exists:barangays,id',
            ],
        ];
    }

    protected function defaultSort(): string
    {
        return 'term_start';
    }

    protected function sortableColumns(): array
    {
        return [

            'term_start',

        ];
    }
}
