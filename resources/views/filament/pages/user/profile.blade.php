<div class="filament-page">
    <h1 class="text-2xl font-bold">Profile</h1>

    <div class="mt-6 bg-white p-6 rounded shadow">
        <p>{{ auth()->user()->name }} &lt;{{ auth()->user()->email }}&gt;</p>
        <p class="mt-2 text-sm text-gray-600">Use account settings in Filament to update your profile.</p>
    </div>
</div>
