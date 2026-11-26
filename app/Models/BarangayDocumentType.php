<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class BarangayDocumentType extends Modelimplements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'document_type_id',
        'barangay_id',
        'fee',
        'requires_payment',
        'allow_online_payment',
        'requires_chairperson_signature',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'requires_payment' => 'boolean',
            'allow_online_payment' => 'boolean',
            'requires_chairperson_signature' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }
}
