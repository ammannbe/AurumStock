<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages\CreateSupplier;
use App\Filament\Resources\SupplierResource\Pages\EditSupplier;
use App\Filament\Resources\SupplierResource\Pages\ListSuppliers;
use App\Models\Supplier;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = null;

    protected static \UnitEnum|string|null $navigationGroup = null;

    public static function getNavigationLabel(): string
    {
        return __('Suppliers');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Management');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(static::formFields());
    }

    /**
     * @return array<int, Component>
     */
    public static function formFields(): array
    {
        return [
            Hidden::make('user_id')->default(fn () => Auth::id()),
            TextInput::make('name')->label(__('Name'))->required()->maxLength(255)->columnSpanFull()->maxWidth('xl'),
            TextInput::make('website')->label(__('Website'))->url()->nullable()->maxLength(255)->columnSpanFull()->maxWidth('xl'),
            Textarea::make('notes')->label(__('Notes'))->nullable()->columnSpanFull()->maxWidth('xl'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $userId = Auth::id();

        $query = parent::getEloquentQuery();

        if (! $userId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('user_id', $userId);
    }

    /**
     * @return array<int|string, string>
     */
    public static function optionsForCurrentUser(): array
    {
        $userId = Auth::id();

        if (! $userId) {
            return [];
        }

        return Supplier::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
            TextColumn::make('website')->label(__('Website'))->url(fn (Supplier $record) => $record->website)->openUrlInNewTab()->toggleable(),
            TextColumn::make('orders_count')->label(__('Orders'))->counts('orders')->sortable(),
            TextColumn::make('notes')->label(__('Notes'))->limit(50)->toggleable(isToggledHiddenByDefault: true),
        ])->recordActions([
            EditAction::make()->color('secondary'),
            DeleteAction::make(),
        ])->toolbarActions([
            DeleteBulkAction::make(),
        ])->defaultSort('name');
    }

    public static function getModelLabel(): string
    {
        return __('Supplier');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Suppliers');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
