<?php

namespace App\Models;

use App\Enums\SharePermission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Depository $depository
 */
/**
 * @method static \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DepositoryShare> factory(...$parameters)
 */
class DepositoryShare extends Model
{
    use HasFactory;

    protected $fillable = ['depository_id', 'owner_user_id', 'shared_with_user_id', 'permission'];

    protected $casts = [
        'permission' => SharePermission::class,
    ];

    public function depository(): BelongsTo
    {
        return $this->belongsTo(Depository::class);
    }

    public function sharedWith(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
