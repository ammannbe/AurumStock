<?php

namespace App\Enums;

enum WeightUnit: int
{
    case Gram = 1;
    case TroyOunce = 2;

    public function label(): string
    {
        return match ($this) {
            self::Gram => __('Gram'),
            self::TroyOunce => __('Troy Ounce'),
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Gram => 'g',
            self::TroyOunce => 'oz',
        };
    }

    public function toGram(float $value): float
    {
        return match ($this) {
            self::Gram => $value,
            self::TroyOunce => $value * 31.1034768,
        };
    }

    public function toTroyOunce(float $value): float
    {
        return match ($this) {
            self::Gram => $value / 31.1034768,
            self::TroyOunce => $value,
        };
    }
}
