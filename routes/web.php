<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

Route::middleware('auth')->group(function () {
    // Profile and management pages are handled via Filament panels now.
    Route::get('/profile', function () {
        return redirect('/app');
    })->name('profile.edit');

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
});

require __DIR__.'/auth.php';
