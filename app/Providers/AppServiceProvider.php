<?php

namespace App\Providers;

use App\Console\Kernel;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Ensure the application resolves the console kernel to our App Console Kernel
        $this->app->singleton(
            \Illuminate\Contracts\Console\Kernel::class,
            Kernel::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
