<?php

namespace App\Support;

use App\Enums\Currency;
use App\Models\Exchange;
use App\Models\ExchangeHistory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class CurrencyConverter
{
    /** @var array<string, float> */
    protected static array $rateCache = [];

    /** @var array<string, float> */
    protected static array $historicalRateCache = [];

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

    /**
     * Rate that applied on the given date, falling back to the most recent
     * earlier rate because FX data has gaps on weekends and holidays.
     */
    public static function rateOn(DateTimeInterface|string $date, ?string $currency = null): float
    {
        $currency ??= static::preferredCode();

        if ($currency === Currency::USD->value) {
            return 1.0;
        }

        $day = Carbon::parse($date)->endOfDay();
        $cacheKey = $currency.'@'.$day->toDateString();

        if (array_key_exists($cacheKey, static::$historicalRateCache)) {
            return static::$historicalRateCache[$cacheKey];
        }

        $rate = (float) (ExchangeHistory::query()
            ->whereHas('exchange', fn (Builder $query) => $query->where('currency', $currency))
            ->where('fetched_at', '<=', $day)
            ->orderByDesc('fetched_at')
            ->value('value') ?? 0);

        if ($rate <= 0) {
            $rate = static::rate($currency);
        }

        return static::$historicalRateCache[$cacheKey] = $rate;
    }

    public static function fromUsd(float|int|null $amount, ?string $currency = null): float
    {
        return (float) ($amount ?? 0) * static::rate($currency);
    }

    public static function fromUsdOn(float|int|null $amount, DateTimeInterface|string $date, ?string $currency = null): float
    {
        return (float) ($amount ?? 0) * static::rateOn($date, $currency);
    }

    /**
     * Rates for each given day, keyed by Y-m-d.
     *
     * @param  array<int, string>  $days
     * @return array<string, float>
     */
    public static function ratesForDays(array $days, ?string $currency = null): array
    {
        $currency ??= static::preferredCode();

        if ($days === []) {
            return [];
        }

        if ($currency === Currency::USD->value) {
            return array_fill_keys($days, 1.0);
        }

        sort($days);

        // One query for the whole window, then carry each rate forward over gaps.
        $history = ExchangeHistory::query()
            ->whereHas('exchange', fn (Builder $query) => $query->where('currency', $currency))
            ->where('fetched_at', '<=', Carbon::parse((string) end($days))->endOfDay())
            ->orderBy('fetched_at')
            ->get(['value', 'fetched_at'])
            ->mapWithKeys(fn (ExchangeHistory $row): array => [
                $row->fetched_at?->toDateString() ?? '' => (float) $row->value,
            ])
            ->filter(fn (float $value, string $key): bool => $key !== '' && $value > 0);

        $fallback = static::rate($currency);

        /** @var array<string, float> $rates */
        $rates = $history->all();

        // Both lists are sorted, so walk them together and carry the last rate forward.
        $rateDays = array_keys($rates);
        $cursor = 0;
        $carried = null;
        $result = [];

        foreach ($days as $day) {
            while ($cursor < count($rateDays) && $rateDays[$cursor] <= $day) {
                $carried = $rates[$rateDays[$cursor]];
                $cursor++;
            }

            $result[$day] = $carried ?? $fallback;
        }

        return $result;
    }

    public static function flushCache(): void
    {
        static::$rateCache = [];
        static::$historicalRateCache = [];
    }
}
