<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $material
 * @property float $weight
 * @property int $amount
 * @property string|null $front_image_path
 * @property string|null $back_image_path
 * @property array|null $assets
 * @property-read \App\Models\User $owner
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'depository_id', 'name', 'type', 'material', 'weight', 'weight_unit', 'amount',
        'face_value', 'year', 'reference_number', 'size', 'price', 'price_currency',
        'front_image_path', 'back_image_path', 'text_front', 'text_back', 'assets', 'notes',
        'quality', 'certificate', 'manufacturer', 'country_of_origin', 'fineness',
        'packaging', 'mintage', 'purchase_date', 'taxed_date',
    ];

    protected $casts = [
        'assets' => 'array',
        'certificate' => 'boolean',
        'purchase_date' => 'date',
        'taxed_date' => 'date',
        'price' => 'decimal:2',
        'weight' => 'decimal:3',
        'mintage' => 'integer',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function depository()
    {
        return $this->belongsTo(Depository::class);
    }

    public function shares()
    {
        return $this->hasMany(ProductShare::class);
    }

    public function rawMaterialValueUsd(): ?float
    {
        $stock = StockPrice::where('material', $this->material)->first();
        if (! $stock || ! $stock->price_usd_per_gram) {
            return null;
        }

        // weight stored in grams (assume conversion handled elsewhere)
        return (float) ($this->weight * $stock->price_usd_per_gram * $this->amount);
    }
}
