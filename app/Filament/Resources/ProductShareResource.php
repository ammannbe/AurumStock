<?php

namespace App\Filament\Resources;

use App\Enums\SharePermission;
use App\Models\Product;
use App\Models\ProductShare;
use App\Models\User;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductShareResource extends Resource
{
    protected static ?string $model = ProductShare::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = null;

    protected static \UnitEnum|string|null $navigationGroup = null;

    public static function getNavigationLabel(): string
    {
        return __('Product Shares');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Sharing');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Hidden::make('owner_user_id')->default(fn () => auth()->id()),
            Select::make('product_id')->label(__('Product'))->options(fn () => Product::where('user_id', auth()->id())->pluck('name', 'id'))->searchable()->required(),
            Select::make('shared_with_user_id')->label(__('Share With'))->options(fn () => User::pluck('name', 'id'))->searchable()->required(),
            Select::make('permission')->label(__('Permission'))->options([SharePermission::View->value => SharePermission::View->label(), SharePermission::Edit->value => SharePermission::Edit->label()])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('product.name')->label(__('Product')),
            TextColumn::make('sharedWith.name')->label(__('Shared With')),
            TextColumn::make('permission')->label(__('Permission'))->getStateUsing(fn ($record) => SharePermission::from($record->permission)->label()),
        ])->defaultSort('id');
    }
}
