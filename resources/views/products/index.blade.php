<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Your Products') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <div class="mb-4 flex justify-between">
        <h2 class="text-xl font-semibold">Your Products</h2>
        <div class="flex gap-2">
            <a href="{{ route('products.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded">New Product</a>
            <a href="{{ route('products.export') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded">Export CSV</a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 text-green-600">{{ session('success') }}</div>
    @endif

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <table class="w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Image</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Material</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Weight</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Depository</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Year</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($products as $product)
                    <tr>
                        <td class="px-4 py-2">
                            <div class="w-20 h-auto bg-gray-100 flex items-center justify-center overflow-hidden">
                                @if(!empty($product->front_image_path))
                                    <img src="{{ asset('storage/' . $product->front_image_path) }}" alt="{{ $product->name }}" class="object-cover w-20 h-auto">
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-2 align-top">
                            <div class="font-medium">{{ $product->name }}</div>
                        </td>
                        <td class="px-4 py-2 align-top text-sm text-gray-600">
                            {{ \App\Enums\Material::tryFrom($product->material)?->name ?? $product->material }}
                        </td>
                        <td class="px-4 py-2 align-top text-sm text-gray-600">
                            {{ $product->weight }} {{ \App\Enums\WeightUnit::tryFrom($product->weight_unit)?->name ?? $product->weight_unit }}
                        </td>
                        <td class="px-4 py-2 align-top text-sm text-gray-600">{{ $product->amount }}</td>
                        <td class="px-4 py-2 align-top text-sm text-gray-600">{{ $product->depository?->name ?? '-' }}</td>
                        <td class="px-4 py-2 align-top text-sm text-gray-600">
                            @if($product->price)
                                {{ number_format($product->price, 2) }} {{ $product->price_currency ?? '' }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-4 py-2 align-top text-sm text-gray-600">{{ $product->year ?? '-' }}</td>
                        <td class="px-4 py-2 align-top text-sm text-gray-600">{{ $product->reference_number ?? '-' }}</td>
                        <td class="px-4 py-2 align-top text-right">
                            <a href="{{ route('products.show', $product) }}" class="text-blue-600 mr-2">View</a>
                            <a href="{{ route('products.edit', $product) }}" class="text-indigo-600 mr-2">Edit</a>
                            <form action="{{ route('products.destroy', $product) }}" method="POST" class="inline">@csrf @method('DELETE')<button class="text-red-600" onclick="return confirm('Delete?')">Delete</button></form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
    </div>
</x-app-layout>
