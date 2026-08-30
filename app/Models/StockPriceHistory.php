<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockPriceHistory> factory(...$parameters)
 */
class StockPriceHistory extends Model
{
    use HasFactory;

    protected $fillable = ['stock_price_id', 'price', 'fetched_at'];

    protected $casts = [
        'price' => 'decimal:6',
        'fetched_at' => 'datetime',
    ];

    public function stockPrice(): BelongsTo
    {
        return $this->belongsTo(StockPrice::class);
    }
}
