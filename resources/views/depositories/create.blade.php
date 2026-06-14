<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Create Depository') }}</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto py-6">
        <form action="{{ route('depositories.store') }}" method="POST">
            @csrf

            <div class="bg-white shadow rounded-lg p-6">
                @if ($errors->any())
                    <div class="mb-4 rounded bg-red-50 border border-red-200 p-3">
                        <ul class="text-sm text-red-700">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" class="mt-1 block max-w-md w-full border-gray-300 rounded-md" required>
                        @error('name')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" class="mt-1 block w-full border-gray-300 rounded-md">{{ old('description') }}</textarea>
                        @error('description')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="md:col-span-2 mt-4">
                        <button class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
                        <a href="{{ route('depositories.index') }}" class="ml-2 text-gray-600">Cancel</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
