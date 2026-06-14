<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockPrice extends Model
{
    use HasFactory;

    protected $fillable = ['material', 'price_usd_per_gram', 'fetched_at'];

    public function histories()
    {
        return $this->hasMany(StockPriceHistory::class);
    }
}
