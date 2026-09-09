<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Shared range/preset handling for the dashboard stock price charts.
 */
class StockPriceRange
{
    /**
     * Preset ranges, mapped to the number of days they span. Null means all data.
     */
    public const PRESETS = [
        '1d' => 1,
        '1m' => 30,
        '3m' => 90,
        '1y' => 365,
        '5y' => 1825,
        'all' => null,
    ];

    /**
     * Presets offered as shorthand buttons next to the filter dropdown.
     */
    public const SHORTHAND_PRESETS = ['1d', '1m', '3m', '1y', '5y'];

    public const DEFAULT_PRESET = '1y';

    public const SESSION_KEY = 'widgets.stock_price_history.filters';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            '1d' => __('1 day'),
            '1m' => __('1 month'),
            '3m' => __('3 months'),
            '1y' => __('1 year'),
            '5y' => __('5 years'),
            'all' => __('All'),
            'custom' => __('Custom'),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function forPreset(string $preset): array
    {
        $end = now()->toDateString();

        // 'all' maps to null, so check the key rather than using ?? here.
        $days = array_key_exists($preset, self::PRESETS)
            ? self::PRESETS[$preset]
            : self::PRESETS[self::DEFAULT_PRESET];

        if ($days === null) {
            $earliest = DB::table('stock_price_histories')
                ->selectRaw('MIN(DATE(COALESCE(fetched_at, created_at))) as earliest_day')
                ->first()?->earliest_day;

            return [$earliest ? (string) $earliest : now()->subYear()->toDateString(), $end];
        }

        return [now()->subDays($days)->toDateString(), $end];
    }

    public static function presetForDates(?string $start, ?string $end): ?string
    {
        if (! is_string($start) || ! is_string($end)) {
            return null;
        }

        foreach (array_keys(self::PRESETS) as $preset) {
            [$presetStart, $presetEnd] = self::forPreset($preset);

            if ($presetStart === $start && $presetEnd === $end) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * @return array{preset: string, startDate: string, endDate: string}
     */
    public static function defaults(): array
    {
        [$start, $end] = self::forPreset(self::DEFAULT_PRESET);

        return [
            'preset' => self::DEFAULT_PRESET,
            'startDate' => $start,
            'endDate' => $end,
        ];
    }

    /**
     * @return array{preset: string, startDate: string, endDate: string}
     */
    public static function fromSession(): array
    {
        $stored = session(self::SESSION_KEY);

        if (! is_array($stored) || ! isset($stored['startDate'], $stored['endDate'])) {
            return self::defaults();
        }

        return [
            'preset' => is_string($stored['preset'] ?? null) ? $stored['preset'] : 'custom',
            'startDate' => (string) $stored['startDate'],
            'endDate' => (string) $stored['endDate'],
        ];
    }
}
