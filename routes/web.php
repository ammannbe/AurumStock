<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});
// Filament handles user and admin panels and pages. No application GET routes needed here.

// Keep auth routes in a separate file.

require __DIR__.'/auth.php';
