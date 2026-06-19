<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductExportController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

// Keep the named dashboard route but redirect into the Filament user panel.
Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Profile and management pages are handled via Filament panels now.
    Route::get('/profile', function () {
        return redirect('/app');
    })->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Keep controller routes for backend actions (store/update/destroy) but redirect GET views into Filament user panel.
    // Products: define GET redirects and preserve store/update/destroy and image/asset routes.
    Route::get('products', function () {
        return redirect('/app/products');
    })->name('products.index');
    Route::get('products/create', function () {
        return redirect('/app/products');
    })->name('products.create');
    Route::get('products/{product}', function () {
        return redirect('/app/products');
    })->where('product', '[0-9]+')->name('products.show');
    Route::get('products/{product}/edit', function () {
        return redirect('/app/products');
    })->where('product', '[0-9]+')->name('products.edit');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::delete('products/{product}/front-image', [ProductController::class, 'destroyFrontImage'])->name('products.front_image.destroy');
    Route::delete('products/{product}/back-image', [ProductController::class, 'destroyBackImage'])->name('products.back_image.destroy');
    Route::delete('products/{product}/assets/{index}', [ProductController::class, 'destroyAsset'])->name('products.assets.destroy');
    Route::get('products/export', [ProductExportController::class, 'export'])->name('products.export');

    // Depositories: similar pattern
    Route::get('depositories', function () {
        return redirect('/app/depositories');
    })->name('depositories.index');
    Route::get('depositories/create', function () {
        return redirect('/app/depositories');
    })->name('depositories.create');
    Route::get('depositories/{depository}', function () {
        return redirect('/app/depositories');
    })->where('depository', '[0-9]+')->name('depositories.show');
    Route::get('depositories/{depository}/edit', function () {
        return redirect('/app/depositories');
    })->where('depository', '[0-9]+')->name('depositories.edit');
    Route::post('depositories', [DepositoryController::class, 'store'])->name('depositories.store');
    Route::put('depositories/{depository}', [DepositoryController::class, 'update'])->name('depositories.update');
    Route::delete('depositories/{depository}', [DepositoryController::class, 'destroy'])->name('depositories.destroy');
});

require __DIR__.'/auth.php';
