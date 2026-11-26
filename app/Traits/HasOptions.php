<?php

namespace App\Traits;

trait HasOptions
{
    public static function options(?callable $valueResolver = null): array
    {
        return collect(self::cases())
            ->map(function ($item) use ($valueResolver) {

                return [

                    'value' => $valueResolver
                        ? $valueResolver($item)
                        : $item->value,

                    'name' => $item->label(),

                ];

            })
            ->values()
            ->toArray();
    }

    public static function values(?callable $valueResolver = null): array
    {
        return array_column(

            self::options($valueResolver),

            'value'

        );
    }
}