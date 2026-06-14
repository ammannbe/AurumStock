<?php

namespace App\Filament\Resources;

use App\Models\StockPrice;
use App\Models\StockPriceHistory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockPriceResource extends Resource
{
    protected static ?string $model = StockPrice::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('material')->options([1 => 'Gold', 2 => 'Silver', 3 => 'Platinum', 4 => 'Palladium'])->required(),
            TextInput::make('price_usd_per_gram')->numeric()->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('material'),
            TextColumn::make('price_usd_per_gram'),
            TextColumn::make('fetched_at'),
        ]);
    }

    public static function afterSave($record)
    {
        // create history entry
        StockPriceHistory::create(['stock_price_id' => $record->id, 'price_usd_per_gram' => $record->price_usd_per_gram]);
    }
}
