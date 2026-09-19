<?php

use App\Http\Controllers\OrderReceiptController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/app/login');
});

// Receipts live on a private disk, so they are streamed behind the policy.
Route::get('/app/orders/{order}/receipts/{index}', OrderReceiptController::class)
    ->whereNumber('index')
    ->middleware('auth')
    ->name('orders.receipts.show');

// Filament handles user and admin panels and pages. No other application GET routes needed here.
