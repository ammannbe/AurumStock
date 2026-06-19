<?php

namespace App\Http\Controllers;

use App\Models\Exchange;
use App\Models\StockPrice;

class DashboardController extends Controller
{
    public function index()
    {
        $stockPrices = StockPrice::orderBy('fetched_at', 'desc')->limit(5)->get();

        $currencies = ['CHF', 'EUR', 'GBP', 'USD'];
        $exchanges = Exchange::whereIn('currency', $currencies)
            ->orderBy('fetched_at', 'desc')
            ->get()
            ->unique('currency')
            ->values()
            ->take(5);

        return view('dashboard', compact('stockPrices', 'exchanges'));
    }
}
