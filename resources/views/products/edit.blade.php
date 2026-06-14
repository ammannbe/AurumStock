<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit Product') }}</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto py-6">
        <form action="{{ route('products.update', $product) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('products._form')
            <div class="mt-4">
                <button class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
                <a href="{{ route('products.index') }}" class="ml-2">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
