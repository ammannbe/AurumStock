<?php

namespace App\Filament\Resources;

use App\Models\Exchange;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExchangeResource extends Resource
{
    protected static ?string $model = Exchange::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = null;

    protected static \UnitEnum|string|null $navigationGroup = null;

    public static function getNavigationLabel(): string
    {
        return __('Exchanges');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Management');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('currency')->label(__('Currency'))->maxLength(3)->required(),
            TextInput::make('value')->label(__('Value'))->numeric()->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('currency')->label(__('Currency')),
            TextColumn::make('value')->label(__('Value')),
            TextColumn::make('fetched_at')->label(__('Updated')),
        ]);
    }
}
