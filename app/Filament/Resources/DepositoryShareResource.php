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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DepositoryShareResource extends Resource
{
    protected static ?string $model = DepositoryShare::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = null;

    protected static \UnitEnum|string|null $navigationGroup = null;

    public static function getNavigationLabel(): string
    {
        return __('Depository Shares');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Sharing');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Hidden::make('owner_user_id')->default(fn () => Auth::id()),
            Select::make('depository_id')->label(__('Depository'))->options(fn () => Depository::where('user_id', Auth::id())->pluck('name', 'id'))->searchable()->required(),
            Select::make('shared_with_user_id')->label(__('Share With'))->options(fn () => User::query()->whereKeyNot(Auth::id())->pluck('name', 'id'))->searchable()->required(),
            Select::make('permission')->label(__('Permission'))->options([SharePermission::View->value => SharePermission::View->label(), SharePermission::Edit->value => SharePermission::Edit->label()])->required(),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('owner_user_id', Auth::id());
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('depository.name')->label(__('Depository')),
            TextColumn::make('sharedWith.name')->label(__('Shared With')),
            TextColumn::make('permission')
                ->label(__('Permission'))
                ->getStateUsing(fn (DepositoryShare $record) => $record->permission->label()),
        ])->defaultSort('id');
    }
}
