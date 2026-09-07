<?php

namespace App\Providers;

use App\Console\Kernel;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Illuminate\Contracts\View\View;
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
        // Filament falls back to its own version for cache busting, which never
        // changes when our own assets do, so derive one from the asset files.
        FilamentAsset::appVersion($this->assetVersion());

        // Register product preview JS for Filament
        FilamentAsset::register([
            Js::make('product-previews')->package('app'),
            Css::make('overlay')->package('app'),
        ]);

        FilamentColor::register([
            'gold' => Color::Amber,
            'silver' => Color::Slate,
            'platinum' => Color::Neutral,
            'palladium' => Color::Gray,
        ]);

        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_START,
            fn (): View => view('filament.tables.product-depository-filter'),
            scopes: ListProducts::class,
        );
    }

    private function assetVersion(): string
    {
        $timestamps = array_map(
            fn (string $path): int => is_file($path) ? (int) filemtime($path) : 0,
            [
                public_path('js/app/product-previews.js'),
                public_path('css/app/overlay.css'),
                public_path('fonts/app/noto-sans-mongolian.woff2'),
            ],
        );

        return (string) max($timestamps);
    }
}
