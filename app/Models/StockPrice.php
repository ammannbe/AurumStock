<?php

namespace App\Models;

use Database\Factories\StockPriceFactory;
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

    protected $fillable = ['material', 'price_usd_per_gram', 'fetched_at'];

    public function histories(): HasMany
    {
        return $this->hasMany(StockPriceHistory::class);
    }
}
