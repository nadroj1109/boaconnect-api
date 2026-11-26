<?php

namespace App\Models;

use App\Enums\DocumentDeliveryMethod;
use App\Enums\DocumentRequestSource;
use App\Enums\DocumentRequestStatus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use OwenIt\Auditing\Contracts\Auditable;

class DocumentRequest extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'request_no',
        'verification_uuid',
        'document_no',
        'source',
        'barangay_id',
        'resident_id',
        'requested_for_resident_id',
        'document_type_id',
        'document_template_id',
        'purpose',
        'remarks',
        'status',
        'delivery_method',
        'requested_at',
        'approved_at',
        'generated_at',
        'signed_at',
        'released_at',
        'approved_by',
        'generated_by',
        'signed_by',
        'released_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentRequestStatus::class,
            'source' => DocumentRequestSource::class,
            'delivery_method' => DocumentDeliveryMethod::class,

            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'generated_at' => 'datetime',
            'signed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function requestedFor()
    {
        return $this->belongsTo(
            Resident::class,
            'requested_for_resident_id'
        );
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function approver()
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }

    public function releaser()
    {
        return $this->belongsTo(
            User::class,
            'released_by'
        );
    }

    public function payments()
    {
        return $this->hasMany(DocumentPayment::class);
    }

    public function files()
    {
        return $this->hasMany(DocumentFile::class);
    }

    public function logs()
    {
        return $this->hasMany(DocumentRequestLog::class)
            ->latest();
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
            DocumentRequestField::class
        );
    }

    public function attachments()
    {
        return $this->hasMany(
            DocumentRequestAttachment::class
        );
    }

    public function generator()
    {
        return $this->belongsTo(
            User::class,
            'generated_by'
        );
    }

    public function signer()
    {
        return $this->belongsTo(
            User::class,
            'signed_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePending($query)
    {
        return $query->where(
            'status',
            DocumentRequestStatus::PENDING
        );
    }

    // public function scopeUnderReview($query)
    // {
    //     return $query->where(
    //         'status',
    //         DocumentRequestStatus::UNDER_REVIEW
    //     );
    // }

    public function scopeApproved($query)
    {
        return $query->where(
            'status',
            DocumentRequestStatus::APPROVED
        );
    }

    public function scopeAwaitingPayment($query)
    {
        return $query->where(
            'status',
            DocumentRequestStatus::AWAITING_PAYMENT
        );
    }

    public function scopePaid($query)
    {
        return $query->where(
            'status',
            DocumentRequestStatus::PAYMENT_RECEIVED
        );
    }

    public function scopeGenerating($query)
    {
        return $query->where(
            'status',
            DocumentRequestStatus::GENERATING
        );
    }

    public function scopeReady($query)
    {
        return $query->where(
            'status',
            DocumentRequestStatus::READY_FOR_RELEASE
        );
    }

    public function scopeReleased($query)
    {
        return $query->where(
            'status',
            DocumentRequestStatus::RELEASED
        );
    }

    public function scopeRejected($query)
    {
        return $query->where(
            'status',
            DocumentRequestStatus::REJECTED
        );
    }

    public function scopeCancelled($query)
    {
        return $query->where(
            'status',
            DocumentRequestStatus::CANCELLED
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isPending(): bool
    {
        return $this->status === DocumentRequestStatus::PENDING;
    }

    // public function isUnderReview(): bool
    // {
    //     return $this->status === DocumentRequestStatus::UNDER_REVIEW;
    // }

    public function isApproved(): bool
    {
        return $this->status === DocumentRequestStatus::APPROVED;
    }

    public function isAwaitingPayment(): bool
    {
        return $this->status === DocumentRequestStatus::AWAITING_PAYMENT;
    }

    public function isPaid(): bool
    {
        return $this->status === DocumentRequestStatus::PAYMENT_RECEIVED;
    }

    public function isPaymentReceived(): bool
    {
        return $this->status === DocumentRequestStatus::PAYMENT_RECEIVED;
    }

    public function isForSignature(): bool
    {
        return $this->status === DocumentRequestStatus::FOR_SIGNATURE;
    }

    public function isGenerating(): bool
    {
        return $this->status === DocumentRequestStatus::GENERATING;
    }

    // public function isReady(): bool
    // {
    //     return $this->status === DocumentRequestStatus::READY_FOR_PICKUP;
    // }

    // public function isReadyForPickup(): bool
    // {
    //     return $this->status === DocumentRequestStatus::READY_FOR_PICKUP;
    // }

    public function isReadyForRelease(): bool
    {
        return $this->status ===
            DocumentRequestStatus::READY_FOR_RELEASE;
    }

    public function isReleased(): bool
    {
        return $this->status === DocumentRequestStatus::RELEASED;
    }

    public function isRejected(): bool
    {
        return $this->status === DocumentRequestStatus::REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === DocumentRequestStatus::CANCELLED;
    }

    /*
    |--------------------------------------------------------------------------
    | Document Helpers
    |--------------------------------------------------------------------------
    */

    public function requiresPayment(): bool
    {
        return $this->documentType?->requiresPayment() ?? false;
    }

    public function allowsOnlinePayment(): bool
    {
        return $this->documentType?->allowsOnlinePayment() ?? false;
    }

    public function latestPayment(): ?DocumentPayment
    {
        return $this->payments()
            ->latest()
            ->first();
    }

    public function latestFile(): ?DocumentFile
    {
        return $this->files()
            ->latest()
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | State Mutators
    |--------------------------------------------------------------------------
    |
    | These methods intentionally DO NOT persist changes.
    | Services are responsible for saving within their transactions.
    |
    */

    // public function markUnderReview(): void
    // {
    //     $this->status = DocumentRequestStatus::UNDER_REVIEW;
    // }

    public function approve(User $user): void
    {
        $this->status = DocumentRequestStatus::APPROVED;
        $this->approved_by = $user->id;
        $this->approved_at = now();
    }

    public function reject(): void
    {
        $this->status = DocumentRequestStatus::REJECTED;
    }

    public function markAwaitingPayment(): void
    {
        $this->status = DocumentRequestStatus::AWAITING_PAYMENT;
    }

    public function markPaid(): void
    {
        $this->status = DocumentRequestStatus::PAYMENT_RECEIVED;
    }

    public function markGenerating(): void
    {
        $this->status = DocumentRequestStatus::GENERATING;
    }

    // public function markGenerated(): void
    // {
    //     $this->generated_at = now();
    // }

    public function markGenerated(User $user): void
    {
        $this->generated_at = now();
        $this->generated_by = $user->id;
    }

    public function markForSignature(): void
    {
        $this->status = DocumentRequestStatus::FOR_SIGNATURE;
    }

    public function sign(User $user): void
    {
        $this->signed_at = now();
        $this->signed_by = $user->id;
    }

    // public function markReady(): void
    // {
    //     $this->status = DocumentRequestStatus::READY_FOR_PICKUP;
    // }

    public function markReadyForRelease(): void
    {
        $this->status =
            DocumentRequestStatus::READY_FOR_RELEASE;
    }

    public function release(User $user): void
    {
        $this->status = DocumentRequestStatus::RELEASED;
        $this->released_by = $user->id;
        $this->released_at = now();
    }

    public function markDownloaded(): void
    {
        $this->status = DocumentRequestStatus::DOWNLOADED;
    }

    public function cancel(): void
    {
        $this->status = DocumentRequestStatus::CANCELLED;
    }


    public function hasDeliveryMethod(): bool
    {
        return $this->delivery_method !== null;
    }

    public function isPickup(): bool
    {
        return $this->delivery_method ===
            DocumentDeliveryMethod::PICKUP;
    }

    public function isDownload(): bool
    {
        return $this->delivery_method ===
            DocumentDeliveryMethod::DOWNLOAD;
    }
}
