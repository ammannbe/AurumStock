<?php

namespace App\Http\Controllers;

use App\Models\DepositoryShare;
use App\Models\ProductShare;
use Illuminate\Http\Request;

class SharesOverviewController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $productShares = ProductShare::where('shared_with_user_id', $user->id)->with('product')->get();
        $depositoryShares = DepositoryShare::where('shared_with_user_id', $user->id)->with('depository')->get();

        return view('profile.shares', compact('productShares', 'depositoryShares'));
    }
}
