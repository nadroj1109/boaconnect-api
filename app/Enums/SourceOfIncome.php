<?php

namespace App\Enums;

use App\Traits\HasOptions;

enum SourceOfIncome: string
{
    use HasOptions;

    case Employment = 'employment';
    case Business = 'business';
    case Remittance = 'remittance';
    case Investments = 'investments';
    case Pension = 'pension';
    case Farming = 'farming';
    case Fishing = 'fishing';
    case Others = 'others';

    /**
     * Display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Employment => 'Employment',
            self::Business => 'Business',
            self::Remittance => 'Remittance',
            self::Investments => 'Investments',
            self::Pension => 'Pension',
            self::Farming => 'Farming',
            self::Fishing => 'Fishing',
            self::Others => 'Others',
        };
    }

    /**
     * Official DILG/PSA code.
     */
    public function code(): string
    {
        return match ($this) {
            self::Employment => '1',
            self::Business => '2',
            self::Remittance => '3',
            self::Investments => '4',
            self::Pension => '5',
            self::Farming => '6',
            self::Fishing => '7',
            self::Others => '8',
        };
    }
}