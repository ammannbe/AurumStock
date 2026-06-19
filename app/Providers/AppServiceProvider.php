<?php

namespace App\Providers;

use App\Console\Kernel;
use Filament\Support\Assets\Css;
use Illuminate\Support\ServiceProvider;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Assets\Js;

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
        // Register product preview JS for Filament
        FilamentAsset::register([
            Js::make('product-previews')->package('app'),
            Css::make('overlay')->package('app'),
        ]);
    }
}
