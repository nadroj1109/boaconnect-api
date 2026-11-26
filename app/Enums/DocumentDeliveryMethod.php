<?php

namespace App\Enums;

enum DocumentDeliveryMethod: string
{
    case PICKUP = 'pickup';
    case DOWNLOAD = 'download';

    public function label(): string
    {
        return match ($this) {
            self::PICKUP => 'Pick Up',
            self::DOWNLOAD => 'Download',
        };
    }
}
