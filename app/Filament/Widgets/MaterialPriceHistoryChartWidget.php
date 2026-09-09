<?php

namespace App\Filament\Widgets;

use App\Enums\Material;
use App\Models\StockPrice;
use App\Support\CurrencyConverter;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

use function Filament\Support\generate_icon_html;

class MaterialPriceHistoryChartWidget extends ChartWidget
{
    // Only ever rendered from StockPriceHistoryChartWidget, never on its own.
    protected static bool $isDiscovered = false;

    public int $materialValue = 1;

    public string $startDate = '';

    public string $endDate = '';

    protected ?string $pollingInterval = '60s';

    // Resolved responsively by the parent widget's stylesheet.
    protected ?string $maxHeight = 'var(--aurum-metal-chart-height)';

    /**
     * Above this many days the chart falls back to weekly averages.
     */
    private const MAX_DAILY_POINTS = 400;

    /**
     * Up to this many days the chart shows each hourly snapshot separately.
     */
    private const MAX_HOURLY_DAYS = 2;

    /**
     * Above this many points the markers are hidden so the line stays readable.
     */
    private const MAX_POINTS_WITH_MARKERS = 60;

    public function getMaterial(): Material
    {
        return Material::tryFrom($this->materialValue) ?? Material::Gold;
    }

    public function getHeading(): string|Htmlable|null
    {
        $material = $this->getMaterial();

        $icon = generate_icon_html($material->icon())?->toHtml() ?? '';

        return new HtmlString(
            '<span class="aurum-metal-chart-heading">'.$icon.'<span>'.e($material->label()).'</span></span>'
        );
    }

    public function getDescription(): string|Htmlable|null
    {
        $price = $this->currentPrice();

        if ($price === null) {
            return __('No data');
        }

        return Number::currency($price, CurrencyConverter::preferredCode(), app()->getLocale(), 2).' / oz';
    }

    private function currentPrice(): ?float
    {
        $stock = StockPrice::query()
            ->where('material', $this->getMaterial())
            ->latest('fetched_at')
            ->latest('id')
            ->first();

        if ($stock === null || blank($stock->price)) {
            return null;
        }

        return CurrencyConverter::fromUsd((float) $stock->price);
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $material = $this->getMaterial();

        [$from, $to] = $this->resolveDateRange();

        $hourly = $from->diffInDays($to) <= self::MAX_HOURLY_DAYS;

        $timestamp = 'COALESCE(stock_price_histories.fetched_at, stock_price_histories.created_at)';

        $query = DB::table('stock_price_histories')
            ->join('stock_prices', 'stock_prices.id', '=', 'stock_price_histories.stock_price_id')
            ->where('stock_prices.material', $material->value)
            ->whereBetween(DB::raw($timestamp), [$from, $to]);

        // Short ranges keep every snapshot so hourly fetches stay visible.
        $rows = ($hourly
            ? $query
                ->selectRaw("{$timestamp} as moment, stock_price_histories.price as avg_price")
                ->orderBy('moment')
            : $query
                ->selectRaw("DATE({$timestamp}) as moment, AVG(stock_price_histories.price) as avg_price")
                ->groupBy(DB::raw("DATE({$timestamp})"))
                ->orderBy('moment')
        )
            ->get()
            ->map(fn ($row): array => [
                'moment' => (string) $row->moment,
                'day' => Carbon::parse((string) $row->moment)->format('Y-m-d'),
                'avg_price_usd' => (float) $row->avg_price,
            ])
            ->values();

        // Convert each day at the rate that applied then, not at today's rate.
        $ratesByDay = CurrencyConverter::ratesForDays(
            $rows->pluck('day')->unique()->values()->all()
        );

        $rows = $rows->map(fn (array $row): array => [
            'moment' => $row['moment'],
            'avg_price' => $row['avg_price_usd'] * ($ratesByDay[$row['day']] ?? 1.0),
        ]);

        $interval = $hourly
            ? 'hour'
            : $this->resolveInterval($rows->pluck('moment')->unique()->count());

        // Bucketing happens in PHP so the query stays portable across drivers.
        $buckets = $rows
            ->groupBy(fn (array $row): string => $this->bucketKey($row['moment'], $interval))
            ->map(fn ($group): float => (float) $group->avg('avg_price'))
            ->sortKeys();

        $labels = $buckets->keys()->all();

        $showPoints = count($labels) <= self::MAX_POINTS_WITH_MARKERS;

        return [
            'datasets' => [
                [
                    'label' => $material->label(),
                    'data' => $buckets->map(fn (float $price): float => round($price, 2))->values()->all(),
                    'borderColor' => $this->borderColor(),
                    'backgroundColor' => $this->backgroundColor(),
                    'fill' => true,
                    'spanGaps' => true,
                    'tension' => 0.3,
                    'borderWidth' => 2,
                    'pointRadius' => $showPoints ? 2 : 0,
                    'pointHoverRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        /** @var array<int, float|null> $values */
        $values = $this->getCachedData()['datasets'][0]['data'] ?? [];

        $values = array_values(array_filter($values, fn ($value): bool => is_numeric($value)));

        $scale = ['beginAtZero' => false];

        if ($values !== []) {
            $min = min($values);
            $max = max($values);
            // A flat series would otherwise collapse into a zero-height axis.
            $padding = $max > $min ? ($max - $min) * 0.05 : max(abs($max) * 0.01, 0.01);

            $scale['suggestedMin'] = round($min - $padding, 2);
            $scale['suggestedMax'] = round($max + $padding, 2);
        }

        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => $scale,
                'x' => ['ticks' => ['maxTicksLimit' => 8]],
            ],
        ];
    }

    private function borderColor(): string
    {
        return match ($this->getMaterial()) {
            Material::Gold => '#eab308',
            Material::Silver => '#6b7280',
            Material::Platinum => '#3b82f6',
            Material::Palladium => '#10b981',
        };
    }

    private function backgroundColor(): string
    {
        return match ($this->getMaterial()) {
            Material::Gold => 'rgba(234, 179, 8, 0.15)',
            Material::Silver => 'rgba(107, 114, 128, 0.15)',
            Material::Platinum => 'rgba(59, 130, 246, 0.15)',
            Material::Palladium => 'rgba(16, 185, 129, 0.15)',
        };
    }

    /**
     * @return 'day'|'week'
     */
    private function resolveInterval(int $dayCount): string
    {
        return $dayCount <= self::MAX_DAILY_POINTS ? 'day' : 'week';
    }

    private function bucketKey(string $moment, string $interval): string
    {
        $date = Carbon::parse($moment);

        return match ($interval) {
            'hour' => $date->startOfHour()->format('Y-m-d H:i'),
            'week' => $date->startOfWeek()->format('Y-m-d'),
            default => $date->format('Y-m-d'),
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(): array
    {
        $start = Carbon::parse($this->startDate !== '' ? $this->startDate : now()->subYear()->toDateString());
        $end = Carbon::parse($this->endDate !== '' ? $this->endDate : now()->toDateString());

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start->startOfDay(), $end->endOfDay()];
    }
}
