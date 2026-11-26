<?php

namespace App\Http\Requests\BarangayOfficial;

use App\Http\Requests\BaseIndexRequest;

class BarangayOfficialAuditIndexRequest extends BaseIndexRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'direction' => $this->input('direction') ?? 'desc',
        ]);
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),

            'event' => [
                'nullable',
                'string',
                'max:100',
            ],

            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],
        ];
    }

    protected function defaultSort(): string
    {
        return 'created_at';
    }

    protected function sortableColumns(): array
    {
        return [
            'created_at',
            'event',
            'id',
        ];
    }
}
