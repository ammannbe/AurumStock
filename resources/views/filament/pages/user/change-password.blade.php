<x-filament-panels::page
    x-on:password-updated-reload.window="setTimeout(() => { window.location.href = $event.detail.url }, 1200)"
>
    <form wire:submit="save">
        {{ $this->form }}

        <x-filament::button type="submit" style="margin-top: 1rem;">
            {{ __('Change Password') }}
        </x-filament::button>
    </form>
</x-filament-panels::page>
