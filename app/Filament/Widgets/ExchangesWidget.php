<?php

namespace App\Filament\Widgets;

use App\Enums\Currency;
use App\Models\Exchange;
use App\Support\CurrencyConverter;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Number;

class ExchangesWidget extends TableWidget
{
    protected static ?string $heading = null;

    protected function getTablePollingInterval(): ?string
    {
        return '60s';
    }

    public function getHeading(): ?string
    {
        return __('Exchange Rates').' — '.$this->headlineRates();
    }

    public function getTableHeading(): ?string
    {
        return __('Exchange Rates');
    }

    /**
     * @return Builder<Exchange>|null
     */
    protected function getTableQuery(): Builder|Relation|null
    {
        return Exchange::query()->orderByRaw(
            'CASE WHEN currency = ? THEN 0 WHEN currency = ? THEN 1 ELSE 2 END, currency ASC',
            [CurrencyConverter::preferredCode(), Currency::USD->value]
        );
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('currency')->label(__('Currency'))->sortable(),
            TextColumn::make('value')->label(__('Rate'))->numeric(decimalPlaces: 4)->sortable(),
            TextColumn::make('fetched_at')->label(__('Updated'))->isoDate()->sortable(),
        ];
    }

    /**
     * Preferred currency first, then USD as the base reference.
     */
    protected function headlineRates(): string
    {
        $preferred = CurrencyConverter::preferredCode();
        $locale = app()->getLocale();

        $parts = [Currency::USD->value.' '.Number::format(1.0, 4, locale: $locale)];

        if ($preferred !== Currency::USD->value) {
            array_unshift($parts, $preferred.' '.Number::format(CurrencyConverter::rate($preferred), 4, locale: $locale));
        }

        return implode(' · ', $parts);
    }
}
