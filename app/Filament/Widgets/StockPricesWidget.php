<?php

namespace App\Filament\Widgets;

use App\Enums\Material;
use App\Models\StockPrice;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Widgets\TableWidget;

class StockPricesWidget extends TableWidget
{
    protected static ?string $heading = 'Stock Prices';

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder | \Illuminate\Database\Eloquent\Relations\Relation | null
    {
        return StockPrice::query()->limit(10);
    }

    protected function getTableColumns(): array
    {
        return [
            BadgeColumn::make('material')->getStateUsing(fn ($record) => $record->material ? (Material::tryFrom($record->material)?->name ?? $record->material) : '-'),
            TextColumn::make('price_usd_per_gram')->label('USD / g')->getStateUsing(fn ($record) => number_format($record->price_usd_per_gram, 2)),
            TextColumn::make('fetched_at')->label('Fetched At')->getStateUsing(fn ($record) => $record->fetched_at ?? $record->updated_at),
        ];
    }
}
