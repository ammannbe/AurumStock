<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Models\Depository;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::where('user_id', auth()->id())->paginate(10);
        /** @var view-string $viewName */
        $viewName = 'products.index';
        return view($viewName, compact('products'));
    }

    public function create()
    {
        $depositories = Depository::where('user_id', auth()->id())->pluck('name', 'id');
        /** @var view-string $viewName */
        $viewName = 'products.create';
        return view($viewName, compact('depositories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'material' => 'required|integer',
            'weight' => 'required|numeric',
            'weight_unit' => 'required|integer',
            'amount' => 'required|integer|min:1',
            'depository_id' => 'required|exists:depositories,id',
            'price' => 'nullable|numeric',
            'price_currency' => 'nullable|string|max:8',
            'year' => 'nullable|integer',
            'reference_number' => 'nullable|string|max:255',
            'front_image' => 'nullable|image|max:5120',
            'back_image' => 'nullable|image|max:5120',
            'assets.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,mp4,pdf|max:10240',
            'notes' => 'nullable|string',
        ]);

        $data['user_id'] = auth()->id();
        $data['type'] = $data['type'] ?? ProductType::Ingot->value;
        $product = Product::create($data);

        // handle images
        if ($request->hasFile('front_image')) {
            $path = $request->file('front_image')->store("products/{$product->id}", 'public');
            $product->update(['front_image_path' => $path]);
        }

        if ($request->hasFile('back_image')) {
            $path = $request->file('back_image')->store("products/{$product->id}", 'public');
            $product->update(['back_image_path' => $path]);
        }

        // handle additional assets (normalize any legacy entries)
        $assets = $this->normalizeAssets($product->assets ?? []);
        if ($request->hasFile('assets')) {
            foreach ($request->file('assets') as $file) {
                $path = $file->store("products/{$product->id}", 'public');
                $assets[] = [
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                ];
            }
            $product->update(['assets' => $assets]);
        }

        return redirect()->route('products.index')->with('success', 'Product created.');
    }

    public function show(Product $product)
    {
        abort_unless($product->user_id === auth()->id(), 403);
        /** @var view-string $viewName */
        $viewName = 'products.show';
        return view($viewName, compact('product'));
    }

    public function edit(Product $product)
    {
        abort_unless($product->user_id === auth()->id(), 403);
        $depositories = Depository::where('user_id', auth()->id())->pluck('name', 'id');

        /** @var view-string $viewName */
        $viewName = 'products.edit';
        return view($viewName, compact('product', 'depositories'));
    }

    public function update(Request $request, Product $product)
    {
        abort_unless($product->user_id === auth()->id(), 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'material' => 'required|integer',
            'weight' => 'required|numeric',
            'weight_unit' => 'required|integer',
            'amount' => 'required|integer|min:1',
            'depository_id' => 'nullable|exists:depositories,id',
            'price' => 'nullable|numeric',
            'price_currency' => 'nullable|string|max:8',
            'year' => 'nullable|integer',
            'reference_number' => 'nullable|string|max:255',
            'front_image' => 'nullable|image|max:5120',
            'back_image' => 'nullable|image|max:5120',
            'assets.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,mp4,pdf|max:10240',
            'notes' => 'nullable|string',
        ]);
        $product->update($data);

        // replace images if provided
        if ($request->hasFile('front_image')) {
            if ($product->front_image_path) {
                Storage::disk('public')->delete($product->front_image_path);
            }
            $path = $request->file('front_image')->store("products/{$product->id}", 'public');
            $product->update(['front_image_path' => $path]);
        }

        if ($request->hasFile('back_image')) {
            if ($product->back_image_path) {
                Storage::disk('public')->delete($product->back_image_path);
            }
            $path = $request->file('back_image')->store("products/{$product->id}", 'public');
            $product->update(['back_image_path' => $path]);
        }

        // append new assets (normalize legacy string entries first)
        $assets = $this->normalizeAssets($product->assets ?? []);
        if ($request->hasFile('assets')) {
            foreach ($request->file('assets') as $file) {
                $path = $file->store("products/{$product->id}", 'public');
                $assets[] = [
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                ];
            }
            $product->update(['assets' => $assets]);
        }

        return redirect()->route('products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        abort_unless($product->user_id === auth()->id(), 403);
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted.');
    }

    public function destroyAsset(Product $product, $index)
    {
        abort_unless($product->user_id === auth()->id(), 403);

        $assets = $this->normalizeAssets($product->assets ?? []);
        $index = (int) $index;
        if (! isset($assets[$index])) {
            return redirect()->back()->with('error', 'Asset not found.');
        }

        $asset = $assets[$index];
        if (! empty($asset['path'])) {
            Storage::disk('public')->delete($asset['path']);
        }

        array_splice($assets, $index, 1);
        $product->assets = $assets;
        $product->save();

        return redirect()->back()->with('success', 'Asset removed.');
    }

    protected function normalizeAssets($assets): array
    {
        $normalized = [];
        if (! is_array($assets)) {
            return $normalized;
        }

        foreach ($assets as $a) {
            if (is_string($a)) {
                $normalized[] = [
                    'path' => $a,
                    'original_name' => basename($a),
                    'mime' => null,
                ];
            } elseif (is_array($a)) {
                // ensure keys exist
                $normalized[] = [
                    'path' => $a['path'] ?? null,
                    'original_name' => $a['original_name'] ?? ($a['path'] ? basename($a['path']) : null),
                    'mime' => $a['mime'] ?? null,
                ];
            }
        }

        return $normalized;
    }

    public function destroyFrontImage(Request $request, Product $product)
    {
        abort_unless($product->user_id === auth()->id(), 403);
        if ($product->front_image_path) {
            Storage::disk('public')->delete($product->front_image_path);
            $product->update(['front_image_path' => null]);
        }
        if ($request->wantsJson() || $request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Front image removed.');
    }

    public function destroyBackImage(Request $request, Product $product)
    {
        abort_unless($product->user_id === auth()->id(), 403);
        if ($product->back_image_path) {
            Storage::disk('public')->delete($product->back_image_path);
            $product->update(['back_image_path' => null]);
        }
        if ($request->wantsJson() || $request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Back image removed.');
    }
}
