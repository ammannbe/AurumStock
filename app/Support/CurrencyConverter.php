<?php

namespace App\Support;

use App\Enums\Currency;
use App\Models\Exchange;
use Illuminate\Support\Facades\Auth;

class CurrencyConverter
{
    /** @var array<string, float> */
    protected static array $rateCache = [];

    public static function preferredCode(): string
    {
        $preferredCurrency = Auth::user()?->preferred_currency;

        if ($preferredCurrency instanceof Currency) {
            return $preferredCurrency->value;
        }

        return Currency::USD->value;
    }

    /**
     * Rate for 1 USD in the given currency, defaulting to the preferred one.
     */
    public static function rate(?string $currency = null): float
    {
        $currency ??= static::preferredCode();

        if ($currency === Currency::USD->value) {
            return 1.0;
        }

        if (array_key_exists($currency, static::$rateCache)) {
            return static::$rateCache[$currency];
        }

        $rate = (float) (Exchange::query()->where('currency', $currency)->value('value') ?? 0);

        return static::$rateCache[$currency] = $rate > 0 ? $rate : 1.0;
    }

    public static function fromUsd(float|int|null $amount, ?string $currency = null): float
    {
        return (float) ($amount ?? 0) * static::rate($currency);
    }

    public static function flushCache(): void
    {
        static::$rateCache = [];
    }
}
