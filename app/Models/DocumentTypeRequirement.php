<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class DocumentTypeRequirement extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'document_type_id',
        'name',
        'description',
        'is_required',
        'allowed_file_types',
        'max_file_size',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'max_file_size' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function documentType()
    {
        return $this->belongsTo(
            DocumentType::class
        );
    }

    public function requestAttachments()
    {
        return $this->hasMany(
            DocumentRequestAttachment::class
        );
    }

    public function scopeActive($query)
    {
        return $query->where(
            'is_active',
            true
        );
    }

    public function isRequired(): bool
    {
        return $this->is_required;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }
}
