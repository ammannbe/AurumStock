<div class="filament-page">
    <h1 class="text-2xl font-bold">Dashboard</h1>

    <div class="mt-6 grid grid-cols-1 gap-6">
        <div class="bg-white p-6 rounded-lg shadow">
            <p>{{ __('You are logged in to the user panel.') }}</p>
        </div>

        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-lg font-semibold">Stock Prices (USD / g)</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-100">
                    <thead>
                        <tr class="text-left text-gray-600">
                            <th class="px-3 py-2">Material</th>
                            <th class="px-3 py-2">Price (USD/g)</th>
                            <th class="px-3 py-2">Fetched At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stockPrices as $sp)
                            <tr class="bg-white">
                                <td class="px-3 py-2">{{ $sp['material'] }}</td>
                                <td class="px-3 py-2">{{ $sp['price_usd_per_gram'] }}</td>
                                <td class="px-3 py-2">
                                    <div class="text-sm text-gray-700">{{ $sp['fetched_at']['exact'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $sp['fetched_at']['human'] }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-3 py-2" colspan="3">No stock prices available.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-lg font-semibold">Exchange Rates (relative to USD)</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-100">
                    <thead>
                        <tr class="text-left text-gray-600">
                            <th class="px-3 py-2">Currency</th>
                            <th class="px-3 py-2">Value</th>
                            <th class="px-3 py-2">Fetched At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($exchanges as $ex)
                            <tr class="bg-white">
                                <td class="px-3 py-2">{{ $ex['currency'] }}</td>
                                <td class="px-3 py-2">{{ $ex['value'] }}</td>
                                <td class="px-3 py-2">
                                    <div class="text-sm text-gray-700">{{ $ex['fetched_at']['exact'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $ex['fetched_at']['human'] }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-3 py-2" colspan="3">No exchange rates available.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
