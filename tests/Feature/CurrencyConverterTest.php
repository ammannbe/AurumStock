<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Models\Exchange;
use App\Models\User;
use App\Support\CurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyConverterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CurrencyConverter::flushCache();
    }

    public function test_usd_is_the_base_currency(): void
    {
        $this->actingAs(User::factory()->create(['preferred_currency' => Currency::USD]));

        $this->assertSame(1.0, CurrencyConverter::rate());
        $this->assertSame(100.0, CurrencyConverter::fromUsd(100));
    }

    public function test_converts_usd_to_preferred_currency(): void
    {
        Exchange::create(['currency' => 'CHF', 'value' => 0.8]);
        $this->actingAs(User::factory()->create(['preferred_currency' => Currency::CHF]));

        $this->assertSame('CHF', CurrencyConverter::preferredCode());
        $this->assertSame(80.0, CurrencyConverter::fromUsd(100));
    }

    public function test_falls_back_to_one_when_rate_is_missing(): void
    {
        $this->actingAs(User::factory()->create(['preferred_currency' => Currency::EUR]));

        $this->assertSame(1.0, CurrencyConverter::rate());
        $this->assertSame(50.0, CurrencyConverter::fromUsd(50));
    }
}
