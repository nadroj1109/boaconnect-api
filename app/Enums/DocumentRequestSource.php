<?php

namespace App\Enums;

enum DocumentRequestSource: string
{
    case ONLINE = 'online';
    case WALK_IN = 'walk_in';

    public function label(): string
    {
        return match ($this) {
            self::ONLINE => 'Online',
            self::WALK_IN => 'Walk-in',
        };
    }
}
