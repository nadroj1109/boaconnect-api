<?php

namespace App\Enums;

enum DocumentRequestStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case AWAITING_PAYMENT = 'awaiting_payment';
    case PAYMENT_RECEIVED = 'payment_received';
    case FOR_SIGNATURE = 'for_signature';
    case GENERATING = 'generating';
    case READY_FOR_RELEASE = 'ready_for_release';
    case RELEASED = 'released';
    case DOWNLOADED = 'downloaded';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {

            self::PENDING =>
                'Pending',

            self::APPROVED =>
                'Approved',

            self::AWAITING_PAYMENT =>
                'Awaiting Payment',

            self::PAYMENT_RECEIVED =>
                'Payment Received',

            self::GENERATING =>
                'Generating Document',

            self::FOR_SIGNATURE =>
                'For Signature',

            self::READY_FOR_RELEASE =>
                'Ready for Release',

            self::RELEASED =>
                'Released',

            self::DOWNLOADED =>
                'Downloaded',

            self::REJECTED =>
                'Rejected',

            self::CANCELLED =>
                'Cancelled',
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {

            self::RELEASED,
            self::REJECTED,
            self::CANCELLED => true,

            default => false,
        };
    }
}