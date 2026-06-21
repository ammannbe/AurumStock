<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Depository $depository
 */
class DepositoryShare extends Model
{
    use HasFactory;

    protected $fillable = ['depository_id', 'owner_user_id', 'shared_with_user_id', 'permission'];

    public function depository(): BelongsTo
    {
        return $this->belongsTo(Depository::class);
    }
}
