<?php

namespace Tests\Feature;

use App\Enums\FaceValueCurrency;
use App\Models\Depository;
use App\Models\Product;
use App\Models\ProductTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceValueCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_stores_a_face_value_currency(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $product = Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'face_value' => 5,
            'face_value_currency' => FaceValueCurrency::NZD->value,
        ]);

        $this->assertSame('NZD', $product->fresh()->face_value_currency);
    }

    public function test_it_is_independent_of_the_purchase_currency(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $product = Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'face_value' => 5,
            'face_value_currency' => FaceValueCurrency::NZD->value,
            'price' => 110,
            'price_currency' => 'CHF',
        ]);

        $product = $product->fresh();

        $this->assertSame('NZD', $product->face_value_currency);
        $this->assertSame('CHF', $product->price_currency);
    }

    public function test_it_is_copied_from_a_template(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $template = ProductTemplate::factory()->create([
            'face_value' => 1000,
            'face_value_currency' => FaceValueCurrency::MNT->value,
        ]);

        $product = Product::fromTemplate($template, [
            'user_id' => $user->id,
            'depository_id' => $depository->id,
        ]);

        $this->assertSame('MNT', $product->face_value_currency);
    }

    public function test_it_is_part_of_the_template_sync(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $template = ProductTemplate::factory()->create([
            'face_value_currency' => FaceValueCurrency::CHF->value,
        ]);

        $product = Product::fromTemplate($template, [
            'user_id' => $user->id,
            'depository_id' => $depository->id,
        ]);

        $this->assertTrue($product->isSynchronizedWithTemplate());

        $template->update(['face_value_currency' => FaceValueCurrency::EUR->value]);

        $product = $product->fresh();

        $this->assertSame(
            ['face_value_currency'],
            array_column($product->templateDifferences(), 'field')
        );

        $product->syncFromTemplate();

        $this->assertSame('EUR', $product->fresh()->face_value_currency);
    }

    public function test_it_is_created_from_a_product(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $product = Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'face_value' => 50,
            'face_value_currency' => FaceValueCurrency::USD->value,
        ]);

        $template = ProductTemplate::createFromProduct($product);

        $this->assertSame('USD', $template->fresh()->face_value_currency);
    }

    public function test_it_stores_a_historic_currency_with_a_long_code(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $product = Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'face_value' => 100,
            'face_value_currency' => FaceValueCurrency::SCHILLING->value,
        ]);

        $template = ProductTemplate::factory()->create([
            'face_value' => 100,
            'face_value_currency' => FaceValueCurrency::SCHILLING->value,
        ]);

        $this->assertSame('SCHILLING', $product->fresh()->face_value_currency);
        $this->assertSame('SCHILLING', $template->fresh()->face_value_currency);
    }

    public function test_it_offers_the_historic_denominations(): void
    {
        $options = FaceValueCurrency::toArray();

        foreach (['KRONE', 'SCHILLING', 'FRANC', 'FLORIN', 'JPY'] as $code) {
            $this->assertSame($code, FaceValueCurrency::from($code)->value);
            $this->assertArrayHasKey($code, $options);
        }
    }

    public function test_it_suggests_a_currency_for_a_country(): void
    {
        $this->assertSame(FaceValueCurrency::EUR, FaceValueCurrency::forCountry('AT'));
        $this->assertSame(FaceValueCurrency::CHF, FaceValueCurrency::forCountry('CH'));
        $this->assertSame(FaceValueCurrency::NZD, FaceValueCurrency::forCountry('CK'));
        $this->assertSame(FaceValueCurrency::MNT, FaceValueCurrency::forCountry('MN'));
        $this->assertSame(FaceValueCurrency::EUR, FaceValueCurrency::forCountry('AT'));
        $this->assertNull(FaceValueCurrency::forCountry(null));
        $this->assertNull(FaceValueCurrency::forCountry('XX'));
    }

    public function test_every_option_has_a_label(): void
    {
        foreach (FaceValueCurrency::cases() as $case) {
            $this->assertStringContainsString($case->value, $case->label());
            $this->assertStringNotContainsString('_', $case->label());
        }

        $this->assertCount(count(FaceValueCurrency::cases()), FaceValueCurrency::toArray());
    }
}
