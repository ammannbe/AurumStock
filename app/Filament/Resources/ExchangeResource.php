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

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('currency')->maxLength(3)->required(),
            TextInput::make('value')->numeric()->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('currency'),
            TextColumn::make('value'),
            TextColumn::make('fetched_at'),
        ]);
    }
}
