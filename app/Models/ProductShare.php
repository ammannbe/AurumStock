<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Product $product
 * @property-read \App\Models\User $sharedWith
 */
class ProductShare extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'owner_user_id', 'shared_with_user_id', 'permission'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sharedWith(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }
}
