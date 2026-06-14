<?php

namespace App\Policies;

use App\Enums\SharePermission;
use App\Models\Depository;
use App\Models\DepositoryShare;
use App\Models\User;

class DepositoryPolicy
{
    public function view(User $user, Depository $depository): bool
    {
        if ($depository->user_id === $user->id) {
            return true;
        }

        if (DepositoryShare::where('depository_id', $depository->id)->where('shared_with_user_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    public function update(User $user, Depository $depository): bool
    {
        if ($depository->user_id === $user->id) {
            return true;
        }

        $share = DepositoryShare::where('depository_id', $depository->id)->where('shared_with_user_id', $user->id)->first();

        return $share && $share->permission === SharePermission::Edit->value;
    }

    public function delete(User $user, Depository $depository): bool
    {
        return $depository->user_id === $user->id;
    }
}
