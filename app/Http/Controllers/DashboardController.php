<?php

namespace App\Http\Controllers;

class DashboardController extends Controller
{
    public function index()
    {
        // Redirect authenticated users into the Filament user panel.
        if (auth()->check()) {
            return redirect('/app');
        }

        return redirect('/login');
    }
}
