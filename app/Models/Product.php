<?php

namespace App\Models;

use App\Enums\Material;
use App\Enums\ProductType;
use App\Enums\WeightUnit;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property Material $material
 * @property ProductType $type
 * @property WeightUnit $weight_unit
 * @property float $weight
 * @property int $amount
 * @property string|null $source_hash
 * @property int|null $template_id
 * @property string|null $front_image_path
 * @property string|null $back_image_path
 * @property array<int,mixed>|null $assets
 *
 * @method static \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product> factory(...$parameters)
 *
 * @property-read User $owner
 * @property-read ProductTemplate|null $template
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'depository_id',
        'template_id',
        'source_hash',
        'name',
        'type',
        'material',
        'weight',
        'weight_unit',
        'amount',
        'face_value',
        'face_value_currency',
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
        'tax_year',
    ];

    protected static function booted(): void
    {
        // Keep the stored hash in step with the template the product is linked to.
        static::saving(function (self $product): void {
            if ($product->template_id === null) {
                $product->source_hash = null;

                return;
            }

            if ($product->isDirty('template_id')) {
                $product->source_hash = $product->template?->source_hash;
            }
        });
    }

    protected $casts = [
        'type' => ProductType::class,
        'assets' => 'array',
        'certificate' => 'boolean',
        'purchase_date' => 'date',
        'tax_year' => 'integer',
        'price' => 'decimal:2',
        'material' => Material::class,
        'weight' => 'decimal:4',
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
                /** @var Exchange|null */
                $exchange = $this->exchange;

                if (! $exchange || ! $exchange->value || ! $this->price) {
                    return null;
                }

                $materialValue = $this->material_value_total;

                if (! $materialValue) {
                    return null;
                }

                $priceInUSD = ($this->price / $exchange->value) * $this->amount;

                return ($materialValue - $priceInUSD) / $priceInUSD * 100;
            },
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromTemplate(ProductTemplate $template, array $attributes = []): self
    {
        $data = [
            'template_id' => $template->id,
            'source_hash' => $template->source_hash,
            'name' => $template->name,
            'type' => $template->type,
            'material' => $template->material,
            'weight' => $template->weight,
            'weight_unit' => $template->weight_unit,
            'face_value' => $template->face_value,
            'face_value_currency' => $template->face_value_currency,
            'year' => $template->year,
            'size' => $template->size,
            'front_image_path' => $template->front_image_path,
            'back_image_path' => $template->back_image_path,
            'text_front' => $template->text_front,
            'text_back' => $template->text_back,
            'assets' => $template->assets,
            'notes' => $template->notes,
            'manufacturer' => $template->manufacturer,
            'country_of_origin' => $template->country_of_origin,
            'fineness' => $template->fineness,
            'mintage' => $template->mintage,
        ];

        return static::query()->create(array_merge($data, $attributes));
    }

    public function isSynchronizedWithTemplate(): bool
    {
        if (! $this->template_id || ! $this->template) {
            return false;
        }

        return $this->templateDifferences() === [];
    }

    /**
     * Fields where this product differs from its template.
     *
     * @return array<int, array{field: string, product: mixed, template: mixed}>
     */
    public function templateDifferences(): array
    {
        $template = $this->template;

        if (! $template) {
            return [];
        }

        $differences = [];

        foreach (ProductTemplate::SYNCED_FIELDS as $field) {
            $productValue = $this->normalizeForComparison($this->getAttribute($field));
            $templateValue = $this->normalizeForComparison($template->getAttribute($field));

            if ($productValue !== $templateValue) {
                $differences[] = [
                    'field' => $field,
                    'product' => $productValue,
                    'template' => $templateValue,
                ];
            }
        }

        return $differences;
    }

    public function syncFromTemplate(): bool
    {
        $template = $this->template;

        if (! $template) {
            return false;
        }

        foreach (ProductTemplate::SYNCED_FIELDS as $field) {
            $this->setAttribute($field, $template->getAttribute($field));
        }

        $this->source_hash = $template->source_hash;

        return $this->save();
    }

    private function normalizeForComparison(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            return (string) (float) $value;
        }

        return $value;
    }

    public function deleteMediaField(string $field): void
    {
        if (! in_array($field, ['front_image_path', 'back_image_path'], true)) {
            return;
        }

        $value = $this->getAttribute($field);

        if (! is_string($value) || $value === '') {
            $this->forceFill([$field => null])->saveQuietly();

            return;
        }

        $templateValue = $this->template?->getAttribute($field);

        if ($templateValue === $value) {
            $this->forceFill([$field => null])->saveQuietly();

            return;
        }

        Storage::disk('public')->delete($value);
        $this->forceFill([$field => null])->saveQuietly();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProductTemplate::class);
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
