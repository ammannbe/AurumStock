<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductShare extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'owner_user_id', 'shared_with_user_id', 'permission'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function sharedWith()
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }
}
