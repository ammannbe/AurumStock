<?php

namespace App\Models;

use Database\Factories\ProductTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'tax_year',
    ];

    protected $casts = [
        'assets' => 'array',
        'certificate' => 'boolean',
        'purchase_date' => 'date',
        'tax_year' => 'integer',
        'price' => 'decimal:2',
        'weight' => 'decimal:4',
        'mintage' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $template): void {
            $template->source_hash = $template->calculateSourceHash();
        });
    }

    public function calculateSourceHash(): string
    {
        $payload = [
            'name' => $this->name,
            'type' => $this->type,
            'material' => $this->material,
            'weight' => $this->weight,
            'weight_unit' => $this->weight_unit,
            'amount' => $this->amount,
            'face_value' => $this->face_value,
            'year' => $this->year,
            'reference_number' => $this->reference_number,
            'size' => $this->size,
            'price' => $this->price,
            'price_currency' => $this->price_currency,
            'front_image_path' => $this->front_image_path,
            'back_image_path' => $this->back_image_path,
            'text_front' => $this->text_front,
            'text_back' => $this->text_back,
            'notes' => $this->notes,
            'quality' => $this->quality,
            'manufacturer' => $this->manufacturer,
            'country_of_origin' => $this->country_of_origin,
            'fineness' => $this->fineness,
            'packaging' => $this->packaging,
            'mintage' => $this->mintage,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'tax_year' => $this->tax_year,
            'certificate' => $this->certificate,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
