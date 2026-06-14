<div class="bg-white shadow rounded-lg p-6">
    @if ($errors->any())
        <div class="mb-4 rounded bg-red-50 border border-red-200 p-3">
            <ul class="text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <label class="block text-sm font-medium text-gray-700">Name</label>
        <input type="text" name="name" value="{{ old('name', $product->name ?? '') }}" class="mt-1 block max-w-md w-full border-gray-300 rounded-md" required>
        @error('name')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Material</label>
        @php $materials = \App\Enums\Material::cases(); @endphp
        <select name="material" class="mt-1 block max-w-md w-full border-gray-300 rounded-md" required>
            @foreach($materials as $m)
                <option value="{{ $m->value }}" {{ (old('material', $product->material ?? '') == $m->value) ? 'selected' : '' }}>{{ $m->name }}</option>
            @endforeach
        </select>
        @error('material')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Weight</label>
        <input type="text" name="weight" value="{{ old('weight', $product->weight ?? '') }}" class="mt-1 block max-w-md w-full border-gray-300 rounded-md" required>
        @error('weight')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Amount</label>
        <input type="number" name="amount" value="{{ old('amount', $product->amount ?? 1) }}" class="mt-1 block max-w-xs w-full border-gray-300 rounded-md" required>
        @error('amount')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Depository</label>
        <select name="depository_id" class="mt-1 block max-w-md w-full border-gray-300 rounded-md" required>
            <option>-- Please select --</option>
            @foreach($depositories ?? [] as $id => $name)
                <option value="{{ $id }}" {{ (old('depository_id', $product->depository_id ?? '') == $id) ? 'selected' : '' }}>{{ $name }}</option>
            @endforeach
        </select>
        @error('depository_id')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Price</label>
        <input type="text" name="price" value="{{ old('price', $product->price ?? '') }}" class="mt-1 block max-w-md w-full border-gray-300 rounded-md">
        @error('price')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Price Currency</label>
        @php $currencies = ['USD','EUR','CHF','GBP']; @endphp
        <select name="price_currency" class="mt-1 block max-w-xs w-full border-gray-300 rounded-md">
            @foreach($currencies as $c)
                <option value="{{ $c }}" {{ (old('price_currency', $product->price_currency ?? 'USD') == $c) ? 'selected' : '' }}>{{ $c }}</option>
            @endforeach
        </select>
        @error('price_currency')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Year</label>
        <input type="number" name="year" value="{{ old('year', $product->year ?? '') }}" class="mt-1 block max-w-xs w-full border-gray-300 rounded-md">
        @error('year')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Weight Unit</label>
        @php $units = \App\Enums\WeightUnit::cases(); @endphp
        <select name="weight_unit" class="mt-1 block max-w-xs w-full border-gray-300 rounded-md" required>
            @foreach($units as $u)
                <option value="{{ $u->value }}" {{ (old('weight_unit', $product->weight_unit ?? '') == $u->value) ? 'selected' : '' }}>{{ $u->name }}</option>
            @endforeach
        </select>
        @error('weight_unit')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Reference Number</label>
        <input type="text" name="reference_number" value="{{ old('reference_number', $product->reference_number ?? '') }}" class="mt-1 block w-full border-gray-300 rounded-md">
        @error('reference_number')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700">Notes</label>
        <textarea name="notes" class="mt-1 block w-full border-gray-300 rounded-md" rows="4">{{ old('notes', $product->notes ?? '') }}</textarea>
        @error('notes')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="mt-4 md:col-span-2">
        <label class="block text-sm font-medium text-gray-700">Front Image</label>
        <input type="file" name="front_image" accept="image/*" class="mt-1 block w-full">
        @if(!empty($product->front_image_path))
            <div class="mt-2">
                <div class="flex items-start gap-4">
                    <img src="{{ asset('storage/' . $product->front_image_path) }}" alt="Front image" class="max-w-xs border rounded">
                    <form action="{{ route('products.front_image.destroy', ['product' => $product]) }}" method="POST" onsubmit="return confirm('Remove front image?')">
                        @csrf
                        @method('DELETE')
                        <button class="inline-flex items-center px-3 py-1 bg-red-600 text-white rounded">Remove front image</button>
                    </form>
                </div>
            </div>
        @endif
        @error('front_image')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="mt-4 md:col-span-2">
        <label class="block text-sm font-medium text-gray-700">Back Image</label>
        <input type="file" name="back_image" accept="image/*" class="mt-1 block w-full">
        @if(!empty($product->back_image_path))
            <div class="mt-2">
                <div class="flex items-start gap-4">
                    <img src="{{ asset('storage/' . $product->back_image_path) }}" alt="Back image" class="max-w-xs border rounded">
                    <form action="{{ route('products.back_image.destroy', ['product' => $product]) }}" method="POST" onsubmit="return confirm('Remove back image?')">
                        @csrf
                        @method('DELETE')
                        <button class="inline-flex items-center px-3 py-1 bg-red-600 text-white rounded">Remove back image</button>
                    </form>
                </div>
            </div>
        @endif
        @error('back_image')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="mt-4 md:col-span-2">
        <label class="block text-sm font-medium text-gray-700">Additional Assets (images, videos, PDFs)</label>
        <input type="file" name="assets[]" multiple class="mt-1 block w-full">
        @if(!empty($product->assets) && is_array($product->assets))
            <ul class="mt-2 space-y-2">
                @foreach($product->assets as $i => $asset)
                    @php
                        $path = is_array($asset) && array_key_exists('path', $asset) ? $asset['path'] : (is_string($asset) ? $asset : null);
                        $name = is_array($asset) && array_key_exists('original_name', $asset) ? $asset['original_name'] : ($path ? basename($path) : (is_string($asset) ? $asset : 'file'));
                        $mime = is_array($asset) && array_key_exists('mime', $asset) ? $asset['mime'] : '';
                    @endphp
                    <li class="flex items-center justify-between">
                        <div>
                            @if($path)
                                <a class="text-blue-600" href="{{ asset('storage/' . $path) }}" target="_blank">{{ $name }}</a>
                            @else
                                <span class="text-gray-800">{{ $name }}</span>
                            @endif
                            @if($mime)
                                <span class="text-gray-500">({{ $mime }})</span>
                            @endif
                        </div>
                        <div>
                            <form action="{{ route('products.assets.destroy', ['product' => $product, 'index' => $i]) }}" method="POST" onsubmit="return confirm('Remove asset?')">
                                @csrf
                                @method('DELETE')
                                <button class="inline-flex items-center px-2 py-1 text-sm bg-red-600 text-white rounded">Remove</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        @error('assets.*')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>
    </div>
</div>
