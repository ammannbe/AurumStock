<?php

namespace App\Providers;

use App\Models\Depository;
use App\Models\Product;
use App\Policies\DepositoryPolicy;
use App\Policies\ProductPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Depository::class, DepositoryPolicy::class);
    }
}
