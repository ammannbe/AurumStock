<?php

namespace App\Policies;

use App\Enums\SharePermission;
use App\Models\DepositoryShare;
use App\Models\Product;
use App\Models\ProductShare;
use App\Models\User;

class ProductPolicy
{
    public function view(User $user, Product $product): bool
    {
        if ($product->user_id === $user->id) {
            return true;
        }

        // direct share
        if (ProductShare::where('product_id', $product->id)->where('shared_with_user_id', $user->id)->exists()) {
            return true;
        }

        // depository share
        if (DepositoryShare::where('depository_id', $product->depository_id)->where('shared_with_user_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    public function update(User $user, Product $product): bool
    {
        if ($product->user_id === $user->id) {
            return true;
        }

        $share = ProductShare::where('product_id', $product->id)->where('shared_with_user_id', $user->id)->first();
        if ($share && $share->permission === SharePermission::Edit->value) {
            return true;
        }

        $dshare = DepositoryShare::where('depository_id', $product->depository_id)->where('shared_with_user_id', $user->id)->first();
        if ($dshare && $dshare->permission === SharePermission::Edit->value) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Product $product): bool
    {
        return $product->user_id === $user->id;
    }
}
