<div class="filament-page">
    <br><br>

    <h1 class="text-2xl font-bold">Dashboard</h1>

    <div class="mt-6 grid grid-cols-1 gap-6">
        <div class="bg-white p-6 rounded-lg shadow">
            <p>{{ __('You are logged in to the user panel.') }}</p>
        </div>

        <br><br>

        <div>
            <div>
                <x-livewire-mount component-class="\App\Filament\Widgets\StockPricesWidget" />
            </div>

            <br><br>

            <div>
                <x-livewire-mount component-class="\App\Filament\Widgets\ExchangesWidget" />
            </div>
        </div>

        <br><br>
    </div>
</div>
