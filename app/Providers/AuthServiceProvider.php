<?php

namespace App\Providers;

use App\Models\Depository;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Policies\DepositoryPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SupplierPolicy;
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
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
    }
}
