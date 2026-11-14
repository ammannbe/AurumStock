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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

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

        // MCP endpoint (routes/ai.php): applied before OAuth, so it is keyed by IP.
        RateLimiter::for('mcp', fn (Request $request): Limit => Limit::perMinute(60)->by('mcp|'.$request->ip()));

        $this->configurePassport();

        // Panel assets: sources in resources/filament, published to public/{js,css}/app by filament:assets.
        FilamentAsset::register([
            Js::make('product-previews', resource_path('filament/js/product-previews.js'))->package('app'),
            Css::make('overlay', resource_path('filament/css/overlay.css'))->package('app'),
            Css::make('dashboard', resource_path('filament/css/dashboard.css'))->package('app'),
            Css::make('receipts', resource_path('filament/css/receipts.css'))->package('app'),
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

    /**
     * OAuth for AI clients connected over MCP (routes/ai.php). mcp:use is their only scope; what a
     * client may do is stored per client in mcp_grants (approval screen, "Connected AI clients").
     */
    private function configurePassport(): void
    {
        Passport::tokensCan([
            Registrar::OAUTH_SCOPE => 'Use AurumStock as an AI client (MCP)',
        ]);

        Passport::tokensExpireIn(now()->addHour());
        Passport::refreshTokensExpireIn(now()->addDays(90));

        Passport::authorizationView(fn (array $parameters): Response => response()->view('passport.authorize', $parameters));
    }
}
