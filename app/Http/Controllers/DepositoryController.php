<?php

namespace App\Http\Controllers;

use App\Models\Depository;
use Illuminate\Http\Request;

class DepositoryController extends Controller
{
    public function index()
    {
        $depositories = Depository::where('user_id', auth()->id())->paginate(10);
        /** @var view-string $viewName */
        $viewName = 'depositories.index';
        return view($viewName, compact('depositories'));
    }

    public function create()
    {
        /** @var view-string $viewName */
        $viewName = 'depositories.create';
        return view($viewName);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $data['user_id'] = auth()->id();

        Depository::create($data);

        return redirect()->route('depositories.index')->with('success', 'Depository created.');
    }

    public function show(Depository $depository)
    {
        abort_unless($depository->user_id === auth()->id(), 403);
        /** @var view-string $viewName */
        $viewName = 'depositories.show';
        return view($viewName, compact('depository'));
    }

    public function edit(Depository $depository)
    {
        abort_unless($depository->user_id === auth()->id(), 403);
        /** @var view-string $viewName */
        $viewName = 'depositories.edit';
        return view($viewName, compact('depository'));
    }

    public function update(Request $request, Depository $depository)
    {
        abort_unless($depository->user_id === auth()->id(), 403);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $depository->update($data);

        return redirect()->route('depositories.index')->with('success', 'Depository updated.');
    }

    public function destroy(Depository $depository)
    {
        abort_unless($depository->user_id === auth()->id(), 403);
        $depository->delete();

        return redirect()->route('depositories.index')->with('success', 'Depository deleted.');
    }
}
