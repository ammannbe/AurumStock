<?php

namespace App\Filament\Resources;

use App\Enums\Material;
use App\Enums\ProductType;
use App\Enums\WeightUnit;
use App\Models\Depository;
use App\Models\Product;
use BackedEnum;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Hidden::make('user_id')->default(fn () => auth()->id()),
            Grid::make(2)->schema([
                TextInput::make('name')->required()->maxLength(255),
                Select::make('depository_id')
                    ->label('Depository')
                    ->options(fn () => Depository::where('user_id', auth()->id())->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('type')
                    ->options(collect(ProductType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->name])->toArray())
                    ->required(),
                Select::make('material')
                    ->options(collect(Material::cases())->mapWithKeys(fn ($c) => [$c->value => $c->name])->toArray())
                    ->required(),
                TextInput::make('weight')->numeric()->required(),
                Select::make('weight_unit')
                    ->options(collect(WeightUnit::cases())->mapWithKeys(fn ($c) => [$c->value => $c->name])->toArray())
                    ->required(),
                TextInput::make('amount')->numeric()->default(1)->required(),
                TextInput::make('price')->numeric()->nullable(),
                TextInput::make('price_currency')->maxLength(3)->nullable(),
                TextInput::make('year')->numeric()->nullable(),
                TextInput::make('reference_number')->nullable(),
                TextInput::make('size')->nullable(),
                Textarea::make('notes')->nullable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            BadgeColumn::make('material')->getStateUsing(fn ($record) => Material::from($record->material)->name),
            TextColumn::make('weight'),
            TextColumn::make('amount'),
            TextColumn::make('depository.name')->label('Depository')->sortable(),
            TextColumn::make('raw_material_value')->label('Raw USD')->getStateUsing(fn ($record) => $record->rawMaterialValueUsd() ? number_format($record->rawMaterialValueUsd(), 2) : '-'),
            TextColumn::make('price')->label('Price'),
            TextColumn::make('year')->sortable(),
        ])->filters([
            SelectFilter::make('material')->options(collect(Material::cases())->mapWithKeys(fn ($c) => [$c->value => $c->name])->toArray()),
            SelectFilter::make('type')->options(collect(ProductType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->name])->toArray()),
            SelectFilter::make('weight_unit')->options(collect(WeightUnit::cases())->mapWithKeys(fn ($c) => [$c->value => $c->name])->toArray()),
            SelectFilter::make('depository_id')->label('Depository')->options(fn () => Depository::where('user_id', auth()->id())->pluck('name', 'id')),
            SelectFilter::make('year')->options(fn () => Product::query()->distinct()->pluck('year', 'year')->filter()->toArray()),
            SelectFilter::make('price_currency')->label('Currency')->options(fn () => Product::query()->distinct()->pluck('price_currency', 'price_currency')->filter()->toArray()),
        ])->defaultSort('name');
    }

    public static function getModelLabel(): string
    {
        return 'Product';
    }
}
