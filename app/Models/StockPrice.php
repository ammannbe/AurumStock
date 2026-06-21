<?php

namespace App\Models;

use App\Enums\Material;
use Database\Factories\StockPriceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockPrice> factory(...$parameters)
 */
class StockPrice extends Model
{
    /** @use HasFactory<StockPriceFactory> */
    use HasFactory;

    protected $fillable = ['material', 'price', 'fetched_at'];

    public function histories(): HasMany
    {
        return $this->hasMany(StockPriceHistory::class);
    }

    public function scopeMaterial(Builder $query, Material $material): Builder
    {
        return $query->where('material', $material);
    }
}
