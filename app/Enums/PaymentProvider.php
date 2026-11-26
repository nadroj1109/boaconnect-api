<?php

namespace App\Enums;

enum PaymentProvider: string
{
    case PAYMONGO = 'paymongo';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {

            self::PAYMONGO => 'PayMongo',
            self::MANUAL => 'Manual',
        };
    }
}