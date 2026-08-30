<?php

namespace App\Filament\Resources\DashboardResource\Pages;

use App\Filament\Resources\DashboardResource;
use App\Models\Exchange;
use App\Models\StockPrice;
use Carbon\Carbon;
use Filament\Resources\Pages\Page;

class Dashboard extends Page
{
    protected static string $resource = DashboardResource::class;

    protected string $view = 'filament.pages.user.dashboard';

    /** @var array<int, array<string,mixed>> */
    public array $stockPrices = [];

    /** @var array<int, array<string,mixed>> */
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

        $this->stockPrices = StockPrice::query()->limit(10)->get()->map(function ($p) use ($format) {
            return [
                'material' => $p->material->label(),
                'price' => number_format((float) $p->price, 4),
                'fetched_at' => $format($p->fetched_at ?? $p->updated_at),
            ];
        })->toArray();

        $this->exchanges = Exchange::query()->limit(10)->get()->map(function ($e) use ($format) {
            return [
                'currency' => $e->currency,
                'value' => number_format((float) $e->value, 4),
                'fetched_at' => $format($e->fetched_at ?? $e->updated_at),
            ];
        })->toArray();
    }
}
