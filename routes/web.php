<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/app/login');
});
// Filament handles user and admin panels and pages. No application GET routes needed here.
