<?php

namespace Tests\Feature;

use App\Enums\Material;
use App\Models\Exchange;
use App\Models\StockPrice;
use App\Models\StockPriceHistory;
use App\Models\User;
use App\Support\CurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CurrencyConverter::flushCache();

        Exchange::create(['currency' => 'USD', 'value' => 1.0]);

        foreach (Material::cases() as $material) {
            $stockPrice = StockPrice::create([
                'material' => $material->value,
                'price' => 100 * $material->value,
                'fetched_at' => now(),
            ]);

            foreach (range(0, 10) as $offset) {
                StockPriceHistory::create([
                    'stock_price_id' => $stockPrice->id,
                    'price' => 100 * $material->value + $offset,
                    'fetched_at' => now()->subDays($offset),
                ]);
            }
        }
    }

    public function test_the_dashboard_renders_every_widget(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/app/dashboard');

        $response->assertOk()
            ->assertSee(__('Stock Prices'))
            ->assertSee(__('Exchange Rates'))
            ->assertSee(__('Stock Price History').' (USD)');

        foreach (Material::cases() as $material) {
            $response->assertSee($material->label());
        }
    }

    public function test_guests_cannot_reach_the_dashboard(): void
    {
        $this->get('/app/dashboard')->assertRedirect();
    }
}
