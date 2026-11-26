<?php

namespace App\Services;

use App\Models\DocumentType;
use App\Models\DocumentTypeField;

class DocumentTypeFieldService
{
    public function index(
        DocumentType $documentType
    ) {
        return $documentType
            ->fields()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function store(
        DocumentType $documentType,
        array $data
    ): DocumentTypeField {

        $data['document_type_id'] =
            $documentType->id;

        return DocumentTypeField::create(
            $data
        );
    }

    public function update(
        DocumentTypeField $field,
        array $data
    ): DocumentTypeField {

        if (! empty($data['option_source'])) {
            $data['options'] = null;
        }
    
        $field->update(
            $data
        );

        return $field->refresh();
    }

    public function destroy(
        DocumentTypeField $field
    ): void {

        $field->delete();
    }
}