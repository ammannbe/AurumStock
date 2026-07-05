<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <x-filament::button type="submit" style="margin-top: 1rem;">
            {{ __('Change Password') }}
        </x-filament::button>
    </form>
</x-filament-panels::page>
