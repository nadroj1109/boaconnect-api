<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class BlockchainAnchor extends Model
{
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_PAYMENT = 'payment';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_MISMATCH = 'mismatch';

    protected $fillable = [
        'uuid',
        'document_file_id',
        'document_payment_id',
        'proof_type',
        'proof_version',
        'proof_hash',
        'network',
        'chain_id',
        'contract_address',
        'transaction_hash',
        'submission_claim_token',
        'submission_claimed_at',
        'block_number',
        'status',
        'attempts',
        'last_error',
        'submitted_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'document_file_id' => 'integer',
            'document_payment_id' => 'integer',
            'proof_version' => 'integer',
            'chain_id' => 'integer',
            'block_number' => 'integer',
            'attempts' => 'integer',
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'submission_claimed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BlockchainAnchor $anchor): void {
            if (empty($anchor->uuid)) {
                $anchor->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (BlockchainAnchor $anchor): void {
            $hasDocument = $anchor->document_file_id !== null;
            $hasPayment = $anchor->document_payment_id !== null;

            if ($hasDocument === $hasPayment) {
                throw new LogicException(
                    'A blockchain anchor must reference exactly one source record.'
                );
            }

            if (
                $hasDocument &&
                $anchor->proof_type !== self::TYPE_DOCUMENT
            ) {
                throw new LogicException(
                    'A document file anchor must have proof_type=document.'
                );
            }

            if (
                $hasPayment &&
                $anchor->proof_type !== self::TYPE_PAYMENT
            ) {
                throw new LogicException(
                    'A payment anchor must have proof_type=payment.'
                );
            }
        });
    }

    public function documentFile(): BelongsTo
    {
        return $this->belongsTo(DocumentFile::class);
    }

    public function documentPayment(): BelongsTo
    {
        return $this->belongsTo(DocumentPayment::class);
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isPending(): bool
    {
        return in_array(
            $this->status,
            [
                self::STATUS_PENDING,
                self::STATUS_SUBMITTED,
                self::STATUS_FAILED,
            ],
            true
        );
    }
}
