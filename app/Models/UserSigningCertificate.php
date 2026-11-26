<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSigningCertificate extends Model
{
    protected $fillable = [
        'user_id',
        'certificate_path',
        'subject_dn',
        'issuer_dn',
        'serial_number',
        'fingerprint_sha256',
        'valid_from',
        'valid_until',
        'status',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'verified_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'verified_by'
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->valid_from?->isPast()
            && $this->valid_until?->isFuture()
            && is_null($this->revoked_at);
    }
}
