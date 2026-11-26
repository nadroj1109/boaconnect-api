<?php

namespace App\Enums;

enum ComplaintStatus: string
{
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case ASSIGNED = 'assigned';
    case SCHEDULED = 'scheduled';
    case HEARING = 'hearing';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::SUBMITTED => 'Submitted',
            self::UNDER_REVIEW => 'Under Review',
            self::ASSIGNED => 'Assigned',
            self::SCHEDULED => 'Scheduled',
            self::HEARING => 'Hearing',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::CLOSED,
            self::REJECTED,
            self::CANCELLED => true,

            default => false,
        };
    }
}
