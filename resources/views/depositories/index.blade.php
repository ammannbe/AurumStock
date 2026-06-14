<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Your Depositories') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="mb-4 flex justify-between">
            <h2 class="text-xl font-semibold">Your Depositories</h2>
            <a href="{{ route('depositories.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded">New Depository</a>
        </div>

        @if(session('success'))
            <div class="mb-4 text-green-600">{{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($depositories as $depo)
                <div class="bg-white shadow rounded-lg p-4">
                    <h3 class="font-semibold">{{ $depo->name }}</h3>
                    <p class="text-sm text-gray-600 mt-2">{{ $depo->description }}</p>
                    <div class="mt-4 text-right">
                        <a href="{{ route('depositories.show', $depo) }}" class="text-blue-600 mr-2">View</a>
                        <a href="{{ route('depositories.edit', $depo) }}" class="text-indigo-600 mr-2">Edit</a>
                        <form action="{{ route('depositories.destroy', $depo) }}" method="POST" class="inline">@csrf @method('DELETE')<button class="text-red-600" onclick="return confirm('Delete?')">Delete</button></form>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $depositories->links() }}</div>
    </div>
</x-app-layout>
