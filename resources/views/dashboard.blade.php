<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{ __("You're logged in!") }}
                    <div class="mt-4">
                        <h3 class="font-semibold">Latest Stock Prices</h3>
                        <ul>
                            @foreach($stockPrices as $sp)
                                <li>{{ $sp->material }}: {{ $sp->price_usd_per_gram }} USD/g (fetched {{ $sp->fetched_at }})</li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="mt-4">
                        <h3 class="font-semibold">Latest Exchange Rates</h3>
                        <ul>
                            @foreach($exchanges as $ex)
                                <li>{{ $ex->currency }}: {{ $ex->value }} (fetched {{ $ex->fetched_at }})</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
