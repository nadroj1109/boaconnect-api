<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequestAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'document_request_id',
        'document_type_requirement_id',
        'filename',
        'path',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(
            DocumentRequest::class
        );
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(
            DocumentTypeRequirement::class,
            'document_type_requirement_id'
        );
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by'
        );
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
