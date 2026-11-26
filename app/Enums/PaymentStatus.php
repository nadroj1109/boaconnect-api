<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';

    case PAID = 'paid';

    case FAILED = 'failed';

    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {

            self::PENDING =>
                'Pending',

            self::PAID =>
                'Paid',

            self::FAILED =>
                'Failed',

            self::CANCELLED =>
                'Cancelled',
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {

            self::PAID,
            self::FAILED,
            self::CANCELLED => true,

            default => false,
        };
    }
}