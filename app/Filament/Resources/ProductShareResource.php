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

    protected static ?string $navigationLabel = 'Product Shares';

    protected static \UnitEnum|string|null $navigationGroup = 'Sharing';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Hidden::make('owner_user_id')->default(fn () => auth()->id()),
            Select::make('product_id')->options(fn () => Product::where('user_id', auth()->id())->pluck('name', 'id'))->searchable()->required(),
            Select::make('shared_with_user_id')->label('Share With')->options(fn () => User::pluck('name', 'id'))->searchable()->required(),
            Select::make('permission')->options([SharePermission::View->value => 'View', SharePermission::Edit->value => 'Edit'])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('product.name')->label('Product'),
            TextColumn::make('sharedWith.name')->label('Shared With'),
            TextColumn::make('permission')->getStateUsing(fn ($record) => SharePermission::from($record->permission)->name),
        ])->defaultSort('id');
    }
}
