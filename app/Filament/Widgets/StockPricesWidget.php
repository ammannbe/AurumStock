<?php

namespace App\Filament\Widgets;

use App\Enums\Material;
use App\Models\StockPrice;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class StockPricesWidget extends TableWidget
{
    protected static ?string $heading = 'Stock Prices';

    /**
     * @return Builder<StockPrice>|null
     */
    protected function getTableQuery(): Builder|Relation|null
    {
        return StockPrice::query()->limit(10);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('material')
                ->badge()
                ->getStateUsing(fn ($record) => $record->material->name . ' (' . $record->material->symbol() . ')')
                ->color(fn ($record) => $record->material->color() ?? null),
            TextColumn::make('price')->label('USD / oz')->getStateUsing(fn ($record) => number_format($record->price, 2)),
            TextColumn::make('fetched_at')->label('Fetched At')->getStateUsing(fn ($record) => $record->fetched_at ?? $record->updated_at),
        ];
    }
}
