<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $depository->name }}</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto py-6">
        <div class="bg-white shadow sm:rounded-lg p-4">
            <dl>
                <dt class="font-medium">Description</dt>
                <dd class="mb-2">{{ $depository->description }}</dd>
            </dl>
        </div>
    </div>
</x-app-layout>
