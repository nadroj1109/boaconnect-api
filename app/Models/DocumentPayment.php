<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

use OwenIt\Auditing\Contracts\Auditable;

class DocumentPayment extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'document_request_id',
        'amount',
        'provider',
        'payment_method',
        'provider_reference',
        'checkout_session_id',
        'checkout_url',
        'reference_no',
        'received_by',
        'status',
        'gateway_response',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',

            'provider' => PaymentProvider::class,

            'payment_method' => PaymentMethod::class,

            'status' => PaymentStatus::class,

            'gateway_response' => 'array',

            'paid_at' => 'datetime',
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

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function blockchainAnchors(): HasMany
    {
        return $this->hasMany(BlockchainAnchor::class);
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
            PaymentStatus::PENDING
        );
    }

    public function scopePaid($query)
    {
        return $query->where(
            'status',
            PaymentStatus::PAID
        );
    }

    public function scopeFailed($query)
    {
        return $query->where(
            'status',
            PaymentStatus::FAILED
        );
    }

    public function scopeCancelled($query)
    {
        return $query->where(
            'status',
            PaymentStatus::CANCELLED
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::PAID;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === PaymentStatus::CANCELLED;
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Helpers
    |--------------------------------------------------------------------------
    */

    public function isPayMongo(): bool
    {
        return $this->provider === PaymentProvider::PAYMONGO;
    }

    public function markPaid(
        ?string $providerReference = null,
        ?string $referenceNo = null,
        ?array $gatewayResponse = null,
    ): void {
        $this->status = PaymentStatus::PAID;

        $this->provider_reference =
            $providerReference ??
            $this->provider_reference;

        $this->reference_no =
            $referenceNo ??
            $this->reference_no;

        $this->gateway_response =
            $gatewayResponse ??
            $this->gateway_response;

        $this->paid_at = now();
    }

    public function markFailed(
        ?array $gatewayResponse = null,
    ): void {
        $this->status = PaymentStatus::FAILED;

        if ($gatewayResponse !== null) {
            $this->gateway_response = $gatewayResponse;
        }
    }

    public function markCancelled(): void
    {
        $this->status = PaymentStatus::CANCELLED;
    }

    public function hasCheckoutSession(): bool
    {
        return ! empty($this->checkout_session_id);
    }

    public function markCheckoutCreated(
        string $checkoutSessionId,
        string $checkoutUrl
    ): void {
        $this->checkout_session_id = $checkoutSessionId;
        $this->checkout_url = $checkoutUrl;
    }
}
