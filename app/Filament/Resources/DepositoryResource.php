<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepositoryResource\Pages\CreateDepository;
use App\Filament\Resources\DepositoryResource\Pages\EditDepository;
use App\Filament\Resources\DepositoryResource\Pages\ListDepositories;
use App\Models\Depository;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DepositoryResource extends Resource
{
    protected static ?string $model = Depository::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Depositories';

    protected static \UnitEnum|string|null $navigationGroup = 'Management';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Hidden::make('user_id')->default(fn () => auth()->id()),
            TextInput::make('name')->required()->maxLength(255)->columnSpanFull()->maxWidth('xl'),
            Textarea::make('description')->nullable()->columnSpanFull()->maxWidth('xl'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('description')->limit(50),
        ])->recordActions([
            EditAction::make()->color('secondary'),
            DeleteAction::make(),
        ])->toolbarActions([
            DeleteBulkAction::make(),
        ])->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDepositories::route('/'),
            'create' => CreateDepository::route('/create'),
            'edit' => EditDepository::route('/{record}/edit'),
        ];
    }
}
