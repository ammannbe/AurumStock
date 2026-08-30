<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Models\Exchange;
use App\Models\ExchangeHistory;
use App\Models\User;
use App\Support\CurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalCurrencyConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CurrencyConverter::flushCache();
    }

    private function seedChfHistory(): Exchange
    {
        $exchange = Exchange::create(['currency' => 'CHF', 'value' => 0.90, 'fetched_at' => '2024-03-01']);

        ExchangeHistory::create(['exchange_id' => $exchange->id, 'value' => 0.80, 'fetched_at' => '2024-01-01']);
        ExchangeHistory::create(['exchange_id' => $exchange->id, 'value' => 0.85, 'fetched_at' => '2024-02-01']);
        ExchangeHistory::create(['exchange_id' => $exchange->id, 'value' => 0.90, 'fetched_at' => '2024-03-01']);

        return $exchange;
    }

    public function test_it_uses_the_rate_that_applied_on_the_given_date(): void
    {
        $this->seedChfHistory();
        $this->actingAs(User::factory()->create(['preferred_currency' => Currency::CHF]));

        $this->assertSame(0.80, CurrencyConverter::rateOn('2024-01-15'));
        $this->assertSame(0.85, CurrencyConverter::rateOn('2024-02-15'));
        $this->assertSame(0.90, CurrencyConverter::rateOn('2024-03-15'));
    }

    public function test_it_carries_the_last_known_rate_over_gaps(): void
    {
        $this->seedChfHistory();
        $this->actingAs(User::factory()->create(['preferred_currency' => Currency::CHF]));

        // No entry exists for this day, so the most recent earlier rate applies.
        $this->assertSame(0.85, CurrencyConverter::rateOn('2024-02-28'));
    }

    public function test_it_falls_back_to_the_current_rate_before_any_history(): void
    {
        $this->seedChfHistory();
        $this->actingAs(User::factory()->create(['preferred_currency' => Currency::CHF]));

        $this->assertSame(0.90, CurrencyConverter::rateOn('2020-01-01'));
    }

    public function test_usd_is_always_one(): void
    {
        $this->seedChfHistory();
        $this->actingAs(User::factory()->create(['preferred_currency' => Currency::USD]));

        $this->assertSame(1.0, CurrencyConverter::rateOn('2024-01-15'));
    }

    public function test_rates_for_days_carries_values_forward(): void
    {
        $this->seedChfHistory();
        $this->actingAs(User::factory()->create(['preferred_currency' => Currency::CHF]));

        $rates = CurrencyConverter::ratesForDays([
            '2024-01-01',
            '2024-01-20',
            '2024-02-01',
            '2024-02-10',
            '2024-03-01',
        ]);

        $this->assertSame([
            '2024-01-01' => 0.80,
            '2024-01-20' => 0.80,
            '2024-02-01' => 0.85,
            '2024-02-10' => 0.85,
            '2024-03-01' => 0.90,
        ], $rates);
    }

    public function test_rates_for_days_matches_rate_on(): void
    {
        $this->seedChfHistory();
        $this->actingAs(User::factory()->create(['preferred_currency' => Currency::CHF]));

        $days = ['2024-01-10', '2024-02-05', '2024-03-20'];
        $bulk = CurrencyConverter::ratesForDays($days);

        foreach ($days as $day) {
            $this->assertSame(CurrencyConverter::rateOn($day), $bulk[$day]);
        }
    }

    public function test_conversion_uses_the_historical_rate(): void
    {
        $this->seedChfHistory();
        $this->actingAs(User::factory()->create(['preferred_currency' => Currency::CHF]));

        $this->assertSame(80.0, CurrencyConverter::fromUsdOn(100, '2024-01-15'));
        $this->assertSame(90.0, CurrencyConverter::fromUsd(100));
    }
}
