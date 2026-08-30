@php
    $record = $getRecord();
    $items = \App\Support\ProductMediaGallery::for($record);
    $first = collect($items)->firstWhere('type', 'image');
    $extra = count($items) - 2;

    // Hover swaps to the back image, so only the dedicated back image qualifies.
    $back = filled($record->back_image_path)
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($record->back_image_path)
        : null;
@endphp

@if ($first)
    <span class="product-preview-wrapper">
        <img
            class="product-preview"
            src="{{ $first['url'] }}"
            alt="{{ $record->name }}"
            data-gallery="{{ json_encode($items) }}"
            @if ($back && $back !== $first['url']) data-back="{{ $back }}" @endif
            style="height: 3rem; min-height: 3rem; width: 3rem; min-width: 3rem; object-fit: contain; border-radius: 0.375rem; margin-right: 0.5rem; cursor: zoom-in;"
        />
        @if ($extra > 0)
            <span class="product-preview-badge">+{{ $extra }}</span>
        @endif
    </span>
@endif
