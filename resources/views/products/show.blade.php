<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $product->name }}</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto py-6">
        <div class="bg-white shadow sm:rounded-lg p-4">
            <dl>
                <dt class="font-medium">Material</dt>
                <dd class="mb-2">{{ $product->material }}</dd>
                <dt class="font-medium">Weight</dt>
                <dd class="mb-2">{{ $product->weight }} {{ $product->weight_unit }}</dd>
                <dt class="font-medium">Amount</dt>
                <dd class="mb-2">{{ $product->amount }}</dd>
                <dt class="font-medium">Price</dt>
                <dd class="mb-2">{{ $product->price }} {{ $product->price_currency }}</dd>
            </dl>
        </div>
    </div>
</x-app-layout>
