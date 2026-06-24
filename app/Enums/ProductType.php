<?php

namespace App\Enums;

enum ProductType: int
{
    case Ingot = 1;
    case Coin = 2;

    public function label(): string
    {
        return match ($this) {
            self::Ingot => __('Ingot'),
            self::Coin => __('Coin'),
        };
    }
}
