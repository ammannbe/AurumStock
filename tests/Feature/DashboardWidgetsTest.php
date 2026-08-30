<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Filament\Widgets\ExchangesWidget;
use App\Filament\Widgets\StockPricesWidget;
use App\Models\Exchange;
use App\Models\StockPrice;
use App\Models\User;
use App\Support\CurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CurrencyConverter::flushCache();
    }

    public function test_stock_prices_are_shown_in_the_preferred_currency(): void
    {
        Exchange::create(['currency' => 'CHF', 'value' => 0.8]);
        StockPrice::create(['material' => 1, 'price' => 100, 'fetched_at' => now()]);

        $this->actingAs(User::factory()->create(['preferred_currency' => Currency::CHF]));

        Livewire::test(StockPricesWidget::class)
            ->assertSee('CHF')
            ->assertDontSee('USD / oz');
    }

    public function test_exchange_rates_are_ordered_preferred_then_usd_then_alphabetically(): void
    {
        foreach (['AUD' => 1.5, 'CHF' => 0.8, 'EUR' => 0.9, 'USD' => 1.0] as $currency => $value) {
            Exchange::create(['currency' => $currency, 'value' => $value]);
        }

        $this->actingAs(User::factory()->create(['preferred_currency' => Currency::EUR]));

        Livewire::test(ExchangesWidget::class)
            ->assertCanSeeTableRecords(Exchange::whereIn('currency', ['EUR', 'USD', 'AUD', 'CHF'])
                ->get()
                ->sortBy(fn (Exchange $e) => match ($e->currency) {
                    'EUR' => '0',
                    'USD' => '1',
                    default => '2'.$e->currency,
                })
                ->values(), inOrder: true);
    }

    public function test_stock_price_history_is_backfilled_once_from_existing_price_records(): void
    {
        $stock = StockPrice::create([
            'material' => 1,
            'price' => 100,
            'fetched_at' => '2024-01-10 09:00:00',
        ]);

        $this->assertDatabaseMissing('stock_price_histories', [
            'stock_price_id' => $stock->id,
        ]);

        $this->artisan('app:backfill-stock-price-histories')->assertSuccessful();

        $this->assertDatabaseHas('stock_price_histories', [
            'stock_price_id' => $stock->id,
            'price' => '100.000000',
            'fetched_at' => '2024-01-10 09:00:00',
        ]);

        $this->assertSame(1, $stock->fresh()->histories()->count());

        $this->artisan('app:backfill-stock-price-histories')->assertSuccessful();

        $this->assertSame(1, $stock->fresh()->histories()->count());
    }
}
