<?php

namespace App\Http\Requests\DocumentType;

use App\Http\Requests\BaseIndexRequest;

class DocumentTypeIndexRequest extends BaseIndexRequest
{
    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function sortableColumns(): array
    {
        return [
            'code',
            'name',
            'processing_days',
        ];
    }
}
