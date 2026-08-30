<?php

namespace App\Policies;

use App\Models\Depository;
use App\Models\User;

class DepositoryPolicy
{
    public function view(User $user, Depository $depository): bool
    {
        return $depository->user_id === $user->id;
    }

    public function update(User $user, Depository $depository): bool
    {
        return $depository->user_id === $user->id;
    }

    public function delete(User $user, Depository $depository): bool
    {
        return $depository->user_id === $user->id;
    }
}
