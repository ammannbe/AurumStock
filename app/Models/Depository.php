<?php

namespace App\Models;

use Database\Factories\DepositoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Depository> factory(...$parameters)
 */
class Depository extends Model
{
    /** @use HasFactory<DepositoryFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'description'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(DepositoryShare::class);
    }
}
