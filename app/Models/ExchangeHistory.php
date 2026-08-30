<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $exchange_id
 * @property numeric-string $value
 * @property Carbon|null $fetched_at
 *
 * @method static \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExchangeHistory> factory(...$parameters)
 */
class ExchangeHistory extends Model
{
    use HasFactory;

    protected $fillable = ['exchange_id', 'value', 'fetched_at'];

    protected $casts = [
        'value' => 'decimal:6',
        'fetched_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Exchange, $this>
     */
    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Exchange::class);
    }
}
