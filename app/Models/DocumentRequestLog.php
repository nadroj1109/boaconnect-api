<?php

namespace App\Models;

use App\Enums\DocumentRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentRequestLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_request_id',
        'status',
        'remarks',
        'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentRequestStatus::class,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function documentRequest()
    {
        return $this->belongsTo(
            DocumentRequest::class
        );
    }

    public function performedBy()
    {
        return $this->belongsTo(
            User::class,
            'performed_by'
        );
    }
}
