<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockPriceHistory extends Model
{
    use HasFactory;

    protected $fillable = ['stock_price_id', 'price_usd_per_gram'];

    public function stockPrice()
    {
        return $this->belongsTo(StockPrice::class);
    }
}
