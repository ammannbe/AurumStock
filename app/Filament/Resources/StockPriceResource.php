<?php

namespace App\Filament\Resources;

use App\Enums\Material;
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

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-trending-up';

    protected static ?string $navigationLabel = null;

    protected static \UnitEnum|string|null $navigationGroup = null;

    public static function getNavigationLabel(): string
    {
        return __('Stock Prices');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Management');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('material')->label(__('Material'))->options([
                Material::Gold->value => Material::Gold->label(),
                Material::Silver->value => Material::Silver->label(),
                Material::Platinum->value => Material::Platinum->label(),
                Material::Palladium->value => Material::Palladium->label(),
            ])->required(),
            TextInput::make('price')->label(__('Price'))->numeric()->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('material')->label(__('Material'))->formatStateUsing(fn ($state) => Material::from((int) $state)->label()),
            TextColumn::make('price')->label(__('Price')),
            TextColumn::make('fetched_at')->label(__('Updated')),
        ]);
    }

    public static function afterSave(StockPrice $record): void
    {
        // create history entry
        StockPriceHistory::create(['stock_price_id' => $record->id, 'price' => $record->price]);
    }
}
