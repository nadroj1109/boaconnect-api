<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case GCASH = 'gcash';

    case GRABPAY = 'grabpay';

    case MAYA = 'maya';

    case CARD = 'card';

    case ONLINE_BANKING = 'online_banking';

    case OTC = 'over_the_counter';

    public function label(): string
    {
        return match ($this) {

            self::GCASH =>
                'GCash',

            self::GRABPAY =>
                'GrabPay',

            self::MAYA =>
                'Maya',

            self::CARD =>
                'Card',

            self::ONLINE_BANKING =>
                'Online Banking',

            self::OTC =>
                'Over the Counter',
        };
    }
}