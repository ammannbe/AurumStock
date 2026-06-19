<?php

namespace App\Filament\Resources\DashboardResource\Pages;

use App\Enums\Material;
use App\Filament\Resources\DashboardResource;
use App\Models\Exchange;
use App\Models\StockPrice;
use Carbon\Carbon;
use Filament\Resources\Pages\Page;

class Dashboard extends Page
{
    protected static string $resource = DashboardResource::class;

    protected string $view = 'filament.pages.user.dashboard';

    public array $stockPrices = [];
    public array $exchanges = [];

    public function mount(): void
    {
        $format = function ($v) {
            if (! $v) {
                return ['exact' => '-', 'human' => '-'];
            }
            if ($v instanceof \DateTimeInterface) {
                $dt = Carbon::instance($v);
            } else {
                try {
                    $dt = Carbon::parse($v);
                } catch (\Throwable $e) {
                    return ['exact' => (string) $v, 'human' => '-'];
                }
            }
            return ['exact' => $dt->format('Y-m-d H:i:s'), 'human' => $dt->diffForHumans()];
        };

        $this->stockPrices = StockPrice::query()->limit(10)->get()->map(fn ($p) => [
            'material' => $p->material ? (Material::tryFrom($p->material)?->name ?? $p->material) : '-',
            'price_usd_per_gram' => number_format($p->price_usd_per_gram, 4),
            'fetched_at' => $format($p->fetched_at ?? $p->updated_at),
        ])->toArray();

        $this->exchanges = Exchange::query()->limit(10)->get()->map(fn ($e) => [
            'currency' => $e->currency,
            'value' => number_format($e->value, 4),
            'fetched_at' => $format($e->fetched_at ?? $e->updated_at),
        ])->toArray();
    }
}
