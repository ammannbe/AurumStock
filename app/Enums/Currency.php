<?php

namespace App\Enums;

enum Currency: string
{
    case USD = 'USD';
    case CHF = 'CHF';
    case EUR = 'EUR';

    /**
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->value])
            ->toArray();
    }
}
