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
                                <li>
                                    {{ $sp->material }}: {{ $sp->price_usd_per_gram }} USD/g
                                    <span class="relative inline-block ml-2 hidden md:inline-block group">
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-200 text-gray-700 text-xs">i</span>
                                        <div class="absolute left-1/2 transform -translate-x-1/2 mt-2 w-max bg-gray-800 text-white text-sm rounded py-1 px-2 opacity-0 group-hover:opacity-100 transition pointer-events-none">
                                            Fetched {{ $sp->fetched_at }}
                                        </div>
                                    </span>
                                    <details class="md:hidden inline-block ml-2">
                                        <summary class="cursor-pointer text-sm text-blue-600">fetched</summary>
                                        <div class="text-sm text-gray-700">Fetched {{ $sp->fetched_at }}</div>
                                    </details>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="mt-4">
                        <h3 class="font-semibold">Latest Exchange Rates (per USD)</h3>
                        <ul>
                            @foreach($exchanges as $ex)
                                <li>
                                    {{ $ex->currency }}: {{ $ex->value }}
                                    <span class="relative inline-block ml-2 hidden md:inline-block group">
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-200 text-gray-700 text-xs">i</span>
                                        <div class="absolute left-1/2 transform -translate-x-1/2 mt-2 w-max bg-gray-800 text-white text-sm rounded py-1 px-2 opacity-0 group-hover:opacity-100 transition pointer-events-none">
                                            Fetched {{ $ex->fetched_at }}
                                        </div>
                                    </span>
                                    <details class="md:hidden inline-block ml-2">
                                        <summary class="cursor-pointer text-sm text-blue-600">fetched</summary>
                                        <div class="text-sm text-gray-700">Fetched {{ $ex->fetched_at }}</div>
                                    </details>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
