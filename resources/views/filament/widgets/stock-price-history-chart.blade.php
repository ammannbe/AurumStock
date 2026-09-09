@php
    $activePreset = $this->getActivePreset();
    $presetLabels = \App\Support\StockPriceRange::options();
    $startDate = $this->getStartDate();
    $endDate = $this->getEndDate();
@endphp

<x-filament-widgets::widget class="fi-wi-chart aurum-metal-charts">
    <x-filament::section :heading="$this->getHeading()">
        <x-slot name="afterHeader">
            <div class="aurum-metal-charts-filters">
                @foreach ($this->getShorthandPresets() as $preset)
                    <x-filament::button
                        size="xs"
                        :color="$activePreset === $preset ? 'primary' : 'gray'"
                        :outlined="$activePreset !== $preset"
                        wire:click="applyPreset('{{ $preset }}')"
                        wire:loading.attr="disabled"
                        wire:target="applyPreset"
                    >
                        {{ $presetLabels[$preset] ?? $preset }}
                    </x-filament::button>
                @endforeach

                <x-filament::dropdown placement="bottom-end" shift width="xs" class="fi-wi-chart-filter">
                    <x-slot name="trigger">
                        {{ $this->getFiltersTriggerAction() }}
                    </x-slot>

                    <div class="fi-wi-chart-filter-content">
                        {{ $this->getFiltersSchema() }}
                    </div>
                </x-filament::dropdown>
            </div>
        </x-slot>

        <div class="aurum-metal-charts-grid">
            @foreach ($this->getMaterials() as $material)
                @livewire(
                    \App\Filament\Widgets\MaterialPriceHistoryChartWidget::class,
                    [
                        'materialValue' => $material->value,
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                    ],
                    key('metal-chart-' . $material->value . '-' . $startDate . '-' . $endDate)
                )
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
