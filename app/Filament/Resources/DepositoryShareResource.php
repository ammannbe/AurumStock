<?php

namespace App\Filament\Resources;

use App\Enums\SharePermission;
use App\Models\Depository;
use App\Models\DepositoryShare;
use App\Models\User;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DepositoryShareResource extends Resource
{
    protected static ?string $model = DepositoryShare::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Hidden::make('owner_user_id')->default(fn () => auth()->id()),
            Select::make('depository_id')->options(fn () => Depository::where('user_id', auth()->id())->pluck('name', 'id'))->searchable()->required(),
            Select::make('shared_with_user_id')->label('Share With')->options(fn () => User::pluck('name', 'id'))->searchable()->required(),
            Select::make('permission')->options([SharePermission::View->value => 'View', SharePermission::Edit->value => 'Edit'])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('depository.name')->label('Depository'),
            TextColumn::make('shared_with_user_id')->label('Shared With')->getStateUsing(fn ($record) => optional($record->sharedWith)->name),
            TextColumn::make('permission')->getStateUsing(fn ($record) => SharePermission::from($record->permission)->name),
        ])->defaultSort('id');
    }
}
