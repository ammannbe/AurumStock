@php
    $options = \App\Filament\Resources\ProductResource::depositoryOptionsForCurrentUser();
@endphp

@if (filled($options))
    <div class="fi-ta-product-depository-filter">
        <label for="{{ $this->getId() }}.table.filters.depository_id" class="fi-sr-only">
            {{ __('Depository') }}
        </label>

        <x-filament::input.wrapper>
            <x-filament::input.select
                id="{{ $this->getId() }}.table.filters.depository_id"
                wire:key="{{ $this->getId() }}.table.filters.depository_id"
                wire:model.live="tableFilters.depository_id.value"
            >
                <option value="">{{ __('Depositories') }}</option>

                @foreach ($options as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>
@endif
