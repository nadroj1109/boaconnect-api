<?php

namespace App\Http\Requests\DocumentRequest;

use App\Enums\DocumentRequestStatus;
use App\Http\Requests\BaseIndexRequest;
use Illuminate\Validation\Rules\Enum;

class DocumentRequestIndexRequest extends BaseIndexRequest
{
    protected function defaultSort(): string
    {
        return 'requested_at';
    }

    protected function sortableColumns(): array
    {
        return [
            'request_no',
            'requested_at',
            'status',
            'created_at',
        ];
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),

            'status' => [
                'nullable',
                new Enum(DocumentRequestStatus::class),
            ],

            'document_type_id' => [
                'nullable',
                'integer',
                'exists:document_types,id',
            ],
        ];
    }
}
