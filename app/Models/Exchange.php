<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $currency
 * @property numeric-string $value
 * @property Carbon|null $fetched_at
 *
 * @method static \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Exchange> factory(...$parameters)
 */
class Exchange extends Model
{
    use HasFactory;

    protected $fillable = ['currency', 'value', 'fetched_at'];

    protected $casts = [
        'value' => 'decimal:6',
        'fetched_at' => 'datetime',
    ];

    /**
     * @return HasMany<ExchangeHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(ExchangeHistory::class);
    }
}
