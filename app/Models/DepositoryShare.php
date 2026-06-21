<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Depository $depository
 */
/**
 * @method static \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DepositoryShare> factory(...$parameters)
 */
class DepositoryShare extends Model
{
    use HasFactory;

    public function depository(): BelongsTo
    {
        return $this->belongsTo(Depository::class);
    }
}
