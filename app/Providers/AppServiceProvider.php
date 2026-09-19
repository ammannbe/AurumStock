<?php

namespace App\Providers;

use App\Console\Kernel;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Support\SwissGermanTranslationLoader;
use Filament\Forms\Components\FileUpload;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
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

        $this->app->extend(
            'translation.loader',
            fn (Loader $loader): Loader => new SwissGermanTranslationLoader($loader),
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
            Css::make('dashboard')->package('app'),
            Css::make('receipts')->package('app'),
        ]);

        // Bundled by Vite because it pulls in pdfjs-dist.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): Htmlable => new HtmlString(Vite::withEntryPoints(['resources/js/pdf-thumbnails.js'])->toHtml()),
        );

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

        // FilePond only bundles a "de" locale, so de_CH would show English.
        FileUpload::configureUsing(fn (FileUpload $upload) => $upload->placeholder(
            fn (): string => __('Drag & Drop your files or :browse', [
                'browse' => '<span class="filepond--label-action">'.__('Browse').'</span>',
            ]),
        ));
    }

    private function assetVersion(): string
    {
        $timestamps = array_map(
            fn (string $path): int => is_file($path) ? (int) filemtime($path) : 0,
            [
                public_path('js/app/product-previews.js'),
                public_path('css/app/overlay.css'),
                public_path('css/app/dashboard.css'),
                public_path('css/app/receipts.css'),
                public_path('fonts/app/noto-sans-mongolian.woff2'),
            ],
        );

        return (string) max($timestamps);
    }
}
