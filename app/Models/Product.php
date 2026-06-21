<?php

namespace App\Models;

use App\Enums\Material;
use App\Enums\WeightUnit;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $material
 * @property float $weight
 * @property int $amount
 * @property string|null $front_image_path
 * @property string|null $back_image_path
 * @property array<int,mixed>|null $assets
 *
 * @method static \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product> factory(...$parameters)
 *
 * @property-read User $owner
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'depository_id',
        'name',
        'type',
        'material',
        'weight',
        'weight_unit',
        'amount',
        'face_value',
        'year',
        'reference_number',
        'size',
        'price',
        'price_currency',
        'front_image_path',
        'back_image_path',
        'text_front',
        'text_back',
        'assets',
        'notes',
        'quality',
        'certificate',
        'manufacturer',
        'country_of_origin',
        'fineness',
        'packaging',
        'mintage',
        'purchase_date',
        'taxed_date',
    ];

    protected $casts = [
        'assets' => 'array',
        'certificate' => 'boolean',
        'purchase_date' => 'date',
        'taxed_date' => 'date',
        'price' => 'decimal:2',
        'material' => Material::class,
        'weight' => 'decimal:3',
        'weight_unit' => WeightUnit::class,
        'mintage' => 'integer',
    ];

    /**
     * @return Attribute<int|float, never>
     */
    protected function materialValue(): Attribute
    {
        return Attribute::make(
            get: function () {
                /** @var StockPrice|null */
                $stock = $this->stockPrice;
                if (! $stock || ! $stock->price) {
                    return null;
                }

                return $this->weight_unit->toTroyOunce($this->weight) * $stock->price;
            },
        );
    }

    /**
     * @return Attribute<int|float, never>
     */
    protected function materialValueTotal(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->material_value * $this->amount;
            },
        );
    }

    /**
     * @return Attribute<string, never>
     */
    protected function weightTotal(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->weight * $this->amount;
            },
        );
    }

    /**
     * @return Attribute<string, never>
     */
    public function priceTrend(): Attribute
    {
        return Attribute::make(
            get: function () {
                /** @var StockPrice|null */
                $stock = $this->stockPrice;
                /** @var Exchange|null */
                $exchange = $this->exchange;

                if (! $stock || ! $stock->price || ! $exchange || ! $exchange->value) {
                    return null;
                }

                $price = $this->price;
                $priceInUSD = $price * $exchange->value;

                return ($stock->price - $priceInUSD) / $priceInUSD * 100;
            },
        );
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function depository(): BelongsTo
    {
        return $this->belongsTo(Depository::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ProductShare::class);
    }

    public function exchange(): HasOne
    {
        return $this->hasOne(
            Exchange::class,
            'currency', // exchanges.currency
            'price_currency'  // products.price_currency
        );
    }

    public function stockPrice(): HasOne
    {
        return $this->hasOne(
            StockPrice::class,
            'material', // stock_prices.material
            'material'  // products.material
        );
    }
}
