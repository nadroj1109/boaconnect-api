<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class DocumentTypeField extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'document_type_id',
        'name',
        'description',
        'field_key',
        'field_type',
        'is_required',
        'options',
        'option_source',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'options' => 'array',
        ];
    }

    public function documentType()
    {
        return $this->belongsTo(
            DocumentType::class
        );
    }

    public function requestFields()
    {
        return $this->hasMany(
            DocumentRequestField::class
        );
    }

    public function scopeActive($query)
    {
        return $query->where(
            'is_active',
            true
        );
    }
}
