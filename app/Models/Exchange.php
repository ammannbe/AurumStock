<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Exchange> factory(...$parameters)
 */
class Exchange extends Model
{
    use HasFactory;

    protected $fillable = ['currency', 'value', 'fetched_at'];
}
