@php
    $receipts = $receipts ?? [];
@endphp

<div class="fi-receipt-preview">
    @forelse ($receipts as $receipt)
        <div class="fi-receipt-preview-item">
            <div class="fi-receipt-preview-frame">
                @if ($receipt['is_pdf'])
                    <embed src="{{ $receipt['url'] }}#toolbar=0" type="application/pdf" />
                @elseif ($receipt['is_image'])
                    <img src="{{ $receipt['url'] }}" alt="{{ $receipt['label'] ?? basename($receipt['path']) }}" />
                @else
                    <p class="fi-receipt-preview-empty">{{ __('Preview not available for this file type.') }}</p>
                @endif
            </div>

            <a
                href="{{ $receipt['url'] }}"
                target="_blank"
                rel="noopener noreferrer"
                class="fi-link fi-size-sm"
            >
                {{ $receipt['label'] ?? basename($receipt['path']) }}
            </a>
        </div>
    @empty
        <p class="fi-receipt-preview-empty">{{ __('No receipts on this order yet.') }}</p>
    @endforelse
</div>
