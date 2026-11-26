<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentRequestField extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_request_id',
        'document_type_field_id',
        'value',
    ];

    public function documentRequest()
    {
        return $this->belongsTo(
            DocumentRequest::class
        );
    }

    public function documentTypeField()
    {
        return $this->belongsTo(
            DocumentTypeField::class
        );
    }

    public function getDisplayValueAttribute(): string
    {
        $field =
            $this->documentTypeField;

        if (! $field) {
            return $this->value;
        }

        if (
            $field->option_source === 'barangays'
        ) {
            return \App\Models\Barangay::query()
                ->whereKey($this->value)
                ->value('barangay')
                ?? $this->value;
        }

        return $this->value;
    }
}
