<?php

use App\Http\Controllers\McpAuthorizationController;
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

// Approval of AI clients (MCP) with the permissions the user ticked, then Passport's approval.
Route::post('/oauth/authorize/mcp', [McpAuthorizationController::class, 'approve'])
    ->middleware('auth')
    ->name('mcp.authorize.approve');

// Filament handles user and admin panels and pages. No other application GET routes needed here.
