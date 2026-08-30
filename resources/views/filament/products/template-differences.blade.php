{{-- Panel CSS has no project Tailwind utilities, so layout is scoped here. --}}
<style>
    .product-template-diff table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
        line-height: 1.25rem;
    }

    .product-template-diff th,
    .product-template-diff td {
        text-align: start;
        padding: 0.5rem 1rem 0.5rem 0;
        vertical-align: top;
    }

    .product-template-diff th:last-child,
    .product-template-diff td:last-child {
        padding-right: 0;
    }

    .product-template-diff th {
        font-weight: 500;
        white-space: nowrap;
    }

    .product-template-diff tbody tr {
        border-top: 1px solid var(--gray-200);
    }

    .dark .product-template-diff tbody tr {
        border-top-color: var(--gray-700);
    }

    .product-template-diff .diff-field {
        font-weight: 500;
        white-space: nowrap;
    }

    .product-template-diff .diff-empty {
        opacity: 0.5;
    }
</style>

<div class="product-template-diff">
    @if (empty($differences))
        <p class="fi-color-gray">
            {{ __('No differences found.') }}
        </p>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ __('Field') }}</th>
                    <th>{{ __('Current Value') }}</th>
                    <th>{{ __('Template Value') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($differences as $difference)
                    <tr>
                        <td class="diff-field">
                            {{ \App\Models\ProductTemplate::fieldLabel($difference['field']) }}
                        </td>
                        <td>
                            @if (blank($difference['product']))
                                <span class="diff-empty">&mdash;</span>
                            @else
                                {{ $difference['product'] }}
                            @endif
                        </td>
                        <td>
                            @if (blank($difference['template']))
                                <span class="diff-empty">&mdash;</span>
                            @else
                                {{ $difference['template'] }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
