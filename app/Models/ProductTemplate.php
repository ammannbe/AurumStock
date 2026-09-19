<?php

namespace App\Models;

use App\Enums\Manufacturer;
use App\Enums\Material;
use App\Enums\ProductType;
use App\Enums\WeightUnit;
use Database\Factories\ProductTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @method static \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductTemplate> factory(...$parameters)
 */
class ProductTemplate extends Model
{
    /** @use HasFactory<ProductTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'source_hash',
        'name',
        'type',
        'material',
        'weight',
        'weight_unit',
        'face_value',
        'face_value_currency',
        'year',
        'size',
        'front_image_path',
        'back_image_path',
        'text_front',
        'text_back',
        'assets',
        'notes',
        'manufacturer',
        'country_of_origin',
        'fineness',
        'mintage',
    ];

    protected $casts = [
        'type' => ProductType::class,
        'material' => Material::class,
        'weight_unit' => WeightUnit::class,
        'assets' => 'array',
        'weight' => 'decimal:4',
        'mintage' => 'integer',
    ];

    /**
     * Fields copied to products and covered by the sync hash.
     *
     * @var array<int, string>
     */
    public const SYNCED_FIELDS = [
        'name',
        'type',
        'material',
        'weight',
        'weight_unit',
        'face_value',
        'face_value_currency',
        'year',
        'size',
        'front_image_path',
        'back_image_path',
        'text_front',
        'text_back',
        'notes',
        'manufacturer',
        'country_of_origin',
        'fineness',
        'mintage',
    ];

    /**
     * Translation keys for synced fields, matching the labels used in the product form.
     *
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'weight' => 'Weight per Item',
        'face_value_currency' => 'Face Value Currency',
        'front_image_path' => 'Front (image side)',
        'back_image_path' => 'Back (value side)',
        'country_of_origin' => 'Country of Origin',
    ];

    public static function fieldLabel(string $field): string
    {
        return __(self::FIELD_LABELS[$field] ?? Str::headline($field));
    }

    /**
     * Human-readable label shown in the product "template" select, generated from
     * the template's own values rather than the (often ambiguous) stored name:
     * coins: "Goldmünze <name> <year>", ingots: "Goldbarren 10 Gramm 999.9 (Metalor)".
     */
    public function displayName(): string
    {
        $manufacturer = $this->manufacturer
            ? (Manufacturer::tryFrom($this->manufacturer)?->label() ?? $this->manufacturer)
            : null;

        if ($this->type === ProductType::Ingot) {
            $weight = rtrim(rtrim(number_format((float) $this->weight, 4, '.', ''), '0'), '.');
            $unit = $this->weight_unit?->label() ?? '';

            $label = implode(' ', array_filter([
                $this->materialTypeLabel(),
                trim("{$weight} {$unit}"),
                $this->fineness,
            ]));

            return $manufacturer ? "{$label} ({$manufacturer})" : $label;
        }

        return implode(' ', array_filter([
            $this->materialTypeLabel(),
            $this->name,
            $this->year,
        ]));
    }

    private function materialTypeLabel(): string
    {
        return match ([$this->material, $this->type]) {
            [Material::Gold, ProductType::Coin] => __('Gold Coin'),
            [Material::Gold, ProductType::Ingot] => __('Gold Ingot'),
            [Material::Silver, ProductType::Coin] => __('Silver Coin'),
            [Material::Silver, ProductType::Ingot] => __('Silver Ingot'),
            [Material::Platinum, ProductType::Coin] => __('Platinum Coin'),
            [Material::Platinum, ProductType::Ingot] => __('Platinum Ingot'),
            [Material::Palladium, ProductType::Coin] => __('Palladium Coin'),
            [Material::Palladium, ProductType::Ingot] => __('Palladium Ingot'),
            default => trim(($this->material?->label() ?? '').' '.($this->type?->label() ?? '')),
        };
    }

    protected static function booted(): void
    {
        static::saving(function (self $template): void {
            $template->source_hash = $template->calculateSourceHash();
        });
    }

    public function calculateSourceHash(): string
    {
        $payload = [];

        foreach (self::SYNCED_FIELDS as $field) {
            $value = $this->getAttribute($field);

            $payload[$field] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public static function createFromProduct(Product $product, ?string $name = null): self
    {
        $attributes = [];

        foreach (self::SYNCED_FIELDS as $field) {
            $attributes[$field] = $product->getAttribute($field);
        }

        $attributes['name'] = $name ?? $product->name;
        $attributes['assets'] = $product->assets;

        $template = static::query()->create($attributes);

        $product->forceFill([
            'template_id' => $template->id,
            'source_hash' => $template->source_hash,
        ])->save();

        return $template;
    }
}
