<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use App\Enums\VerificationChannel;
use App\Enums\VerificationPurpose;

class VerificationCode extends Model
{
    protected $fillable = [

        'user_id',

        'recipient',

        'channel',

        'purpose',

        'code',

        'attempts',

        'verification_token',

        'expires_at',

        'verified_at',

        'consumed_at',

    ];

    protected function casts(): array
    {
        return [
            'channel' => VerificationChannel::class,
            'purpose' => VerificationPurpose::class,
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return now()->greaterThan($this->expires_at);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function markVerified(): void
    {
        logger()->info('Before markVerified', [
            'expires_at' => $this->expires_at?->toDateTimeString(),
            'verified_at' => $this->verified_at?->toDateTimeString(),
        ]);

        $this->update([

            'verified_at' => now(),

            'verification_token' => Str::uuid(),

        ]);

        $this->refresh();

        logger()->info('After markVerified', [
            'expires_at' => $this->expires_at?->toDateTimeString(),
            'verified_at' => $this->verified_at?->toDateTimeString(),
        ]);
    }

    public function consume(): void
    {
        $this->update([

            'consumed_at' => now(),

        ]);
    }

    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    public function isUsable(): bool
    {
        return

            $this->isVerified()

            &&

            ! $this->isConsumed()

            &&

            ! $this->isExpired();
    }
}
