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

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($products as $product)
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="p-4 flex gap-4">
                    <div class="w-24 h-24 flex-shrink-0 bg-gray-100 flex items-center justify-center">
                        @if(!empty($product->front_image_path))
                            <img src="{{ asset('storage/' . $product->front_image_path) }}" alt="{{ $product->name }}" class="object-cover w-24 h-24">
                        @else
                            <span class="text-gray-400">No image</span>
                        @endif
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold">{{ $product->name }}</h3>
                        <p class="text-sm text-gray-600">{{ $product->material }} • {{ $product->weight }} {{ $product->weight_unit }}</p>
                        <div class="mt-3 text-right">
                            <a href="{{ route('products.show', $product) }}" class="text-blue-600 mr-2">View</a>
                            <a href="{{ route('products.edit', $product) }}" class="text-indigo-600 mr-2">Edit</a>
                            <form action="{{ route('products.destroy', $product) }}" method="POST" class="inline">@csrf @method('DELETE')<button class="text-red-600" onclick="return confirm('Delete?')">Delete</button></form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
    </div>
</x-app-layout>
