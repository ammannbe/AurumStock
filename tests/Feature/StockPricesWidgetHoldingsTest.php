<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Filament\Widgets\StockPricesWidget;
use App\Models\Exchange;
use App\Models\Product;
use App\Models\StockPrice;
use App\Models\User;
use App\Support\CurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockPricesWidgetHoldingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CurrencyConverter::flushCache();

        Exchange::create(['currency' => 'USD', 'value' => 1.0]);
        StockPrice::create(['material' => 1, 'price' => 200, 'fetched_at' => now()]);
    }

    public function test_it_shows_the_holdings_total_and_gain_since_the_first_purchase(): void
    {
        $user = User::factory()->create(['preferred_currency' => Currency::USD]);

        Product::factory()->for($user, 'owner')->create([
            'material' => 1,
            'weight' => 1,
            'weight_unit' => 2,
            'amount' => 3,
            'price' => 100,
            'price_currency' => 'USD',
            'purchase_date' => '2020-01-15',
        ]);

        $this->actingAs($user);

        $this->app->setLocale('en');

        // 3 oz at 200 USD is 600 now, bought for 300 → +300 (+100%).
        Livewire::test(StockPricesWidget::class)
            ->assertSee('$600.00')
            ->assertSeeHtml('Gold <span class="aurum-stat-count">('.e(__(':count pcs', ['count' => 3])).')</span>')
            ->assertSee('+$300.00 (100.00%)')
            ->assertSee(__('since :date', ['date' => '01/15/2020']));
    }

    public function test_a_loss_is_marked_up_for_the_red_styling(): void
    {
        $user = User::factory()->create(['preferred_currency' => Currency::USD]);

        // 1 oz worth 200 now, bought for 500 → a loss.
        Product::factory()->for($user, 'owner')->create([
            'material' => 1,
            'weight' => 1,
            'weight_unit' => 2,
            'amount' => 1,
            'price' => 500,
            'price_currency' => 'USD',
        ]);

        $this->actingAs($user);
        $this->app->setLocale('en');

        Livewire::test(StockPricesWidget::class)
            ->assertSeeHtml('<span class="aurum-stat-negative">'.e('−$300.00 (60.00%)').'</span>');
    }

    public function test_a_gain_is_not_marked_up_as_negative(): void
    {
        $user = User::factory()->create(['preferred_currency' => Currency::USD]);

        Product::factory()->for($user, 'owner')->create([
            'material' => 1,
            'weight' => 1,
            'weight_unit' => 2,
            'amount' => 1,
            'price' => 100,
            'price_currency' => 'USD',
        ]);

        $this->actingAs($user);

        Livewire::test(StockPricesWidget::class)
            ->assertDontSeeHtml('aurum-stat-negative');
    }

    public function test_it_ignores_products_of_other_users(): void
    {
        $other = User::factory()->create();

        Product::factory()->for($other, 'owner')->create([
            'material' => 1,
            'weight' => 1,
            'weight_unit' => 2,
            'amount' => 7,
            'price' => 100,
            'price_currency' => 'USD',
        ]);

        $this->actingAs(User::factory()->create(['preferred_currency' => Currency::USD]));

        Livewire::test(StockPricesWidget::class)
            ->assertDontSee(__(':count pcs', ['count' => 7]));
    }
}
