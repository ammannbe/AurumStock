<?php

namespace App\Filament\Widgets;

use App\Models\Exchange;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ExchangesWidget extends TableWidget
{
    protected static ?string $heading = null;

    public function getHeading(): ?string
    {
        return __('Exchange Rates');
    }

    /**
     * @return Builder<Exchange>|null
     */
    protected function getTableQuery(): Builder|Relation|null
    {
        return Exchange::query()->limit(10);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('currency'),
            TextColumn::make('value')->money('usd')->label(__('Rate')),
            TextColumn::make('fetched_at')->label(__('Updated'))->isoDate(),
        ];
    }
}
