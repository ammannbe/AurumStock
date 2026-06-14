<?php

use App\Http\Controllers\DepositoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductExportController;
use App\Http\Controllers\ProfileController;
use App\Models\Exchange;
use App\Models\StockPrice;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $stockPrices = StockPrice::orderBy('fetched_at', 'desc')->limit(5)->get();
    $exchanges = Exchange::orderBy('fetched_at', 'desc')->limit(5)->get();

    return view('dashboard', compact('stockPrices', 'exchanges'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('products/export', [ProductExportController::class, 'export'])->name('products.export');
    Route::resource('products', ProductController::class);
    Route::delete('products/{product}/front-image', [ProductController::class, 'destroyFrontImage'])->name('products.front_image.destroy');
    Route::delete('products/{product}/back-image', [ProductController::class, 'destroyBackImage'])->name('products.back_image.destroy');
    Route::delete('products/{product}/assets/{index}', [ProductController::class, 'destroyAsset'])->name('products.assets.destroy');
    Route::resource('depositories', DepositoryController::class);
});

require __DIR__.'/auth.php';
