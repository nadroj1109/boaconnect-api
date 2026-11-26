<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;

class DocumentType extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        // 'barangay_id',
        'code',
        'name',
        'document_title',
        'description',
        'fee',
        'requires_payment',
        'allow_online_payment',
        'requires_chairperson_signature',
        'template',
        'document_template_id',
        'processing_days',
        'is_active',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'requires_payment' => 'boolean',
            'allow_online_payment' => 'boolean',
            'requires_chairperson_signature' => 'boolean',
            'processing_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // public function barangay()
    // {
    //     return $this->belongsTo(Barangay::class);
    // }

    public function requests()
    {
        return $this->hasMany(DocumentRequest::class);
    }

    public function requirements()
    {
        return $this->hasMany(
            DocumentTypeRequirement::class
        )->orderBy('sort_order');
    }

    public function documentTemplate()
    {
        return $this->belongsTo(
            DocumentTemplate::class
        );
    }

    public function fields()
    {
        return $this->hasMany(
            DocumentTypeField::class
        )->orderBy(
            'sort_order'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function requiresPayment(): bool
    {
        return $this->requires_payment;
    }

    public function allowsOnlinePayment(): bool
    {
        return $this->allow_online_payment;
    }

    public function requiresChairpersonSignature(): bool
    {
        return $this->requires_chairperson_signature;
    }

    public function isFree(): bool
    {
        return ! $this->requires_payment || $this->fee <= 0;
    }

    public function hasRequirements(): bool
    {
        return $this->requirements()
            ->where('is_active', true)
            ->exists();
    }

    public function requiredRequirements()
    {
        return $this->requirements()
            ->where('is_active', true)
            ->where('is_required', true);
    }
}