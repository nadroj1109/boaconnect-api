<?php

namespace App\Enums;

enum ComplaintHearingStatus: string
{
    case SCHEDULED = 'scheduled';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case RESCHEDULED = 'rescheduled';

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Scheduled',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::RESCHEDULED => 'Rescheduled',
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::COMPLETED,
            self::CANCELLED => true,

            self::SCHEDULED,
            self::RESCHEDULED => false,
        };
    }
}
