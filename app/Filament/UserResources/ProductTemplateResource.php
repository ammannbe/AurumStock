<?php

namespace App\Filament\UserResources;

use App\Enums\FaceValueCurrency;
use App\Enums\Fineness;
use App\Enums\Manufacturer;
use App\Enums\Material;
use App\Enums\ProductType;
use App\Enums\WeightUnit;
use App\Filament\UserResources\ProductTemplateResource\Pages\CreateProductTemplate;
use App\Filament\UserResources\ProductTemplateResource\Pages\EditProductTemplate;
use App\Filament\UserResources\ProductTemplateResource\Pages\ListProductTemplates;
use App\Models\ProductTemplate;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ProductTemplateResource extends Resource
{
    protected static ?string $model = ProductTemplate::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->is_admin;
    }

    public static function getNavigationLabel(): string
    {
        return __('Product Templates');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Management');
    }

    public static function getModelLabel(): string
    {
        return __('Product Template');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Product Templates');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make(__('General'))->schema([
                TextInput::make('name')->label(__('Name'))->required()->maxLength(255)->columnSpan(4),
                TextInput::make('year')->label(__('Year'))->numeric()->nullable()->columnSpan(1),
                Select::make('material')
                    ->label(__('Material'))
                    ->options(collect(Material::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                    ->required()
                    ->default(Material::Gold->value)
                    ->columnSpan(4),
                Select::make('type')
                    ->label(__('Type'))
                    ->options(collect(ProductType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                    ->required()
                    ->default(ProductType::Coin->value)
                    ->columnSpan(4),
                Select::make('manufacturer')
                    ->label(__('Manufacturer'))
                    ->options(collect(Manufacturer::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                    ->searchable()
                    ->nullable()
                    ->columnSpan(4),
                Select::make('country_of_origin')
                    ->options([
                        'CH' => __('Switzerland (CH)'),
                        'DE' => __('Germany (DE)'),
                        'AT' => __('Austria (AT)'),
                        'GB' => __('United Kingdom (GB)'),
                        'US' => __('United States (US)'),
                        'CA' => __('Canada (CA)'),
                        'AU' => __('Australia (AU)'),
                        'ZA' => __('South Africa (ZA)'),
                        'FR' => __('France (FR)'),
                        'RU' => __('Russia (RU)'),
                        'CN' => __('China (CN)'),
                        'MX' => __('Mexico (MX)'),
                        'CK' => __('Cook Islands (CK)'),
                        'MN' => __('Mongolia (MN)'),
                        'NU' => __('Niue (NU)'),
                    ])
                    ->label(__('Country of Origin'))
                    ->searchable()
                    ->nullable()
                    ->columnSpan(4),
            ])->columns(8)->columnSpanFull(),

            Section::make(__('Technical data'))->schema([
                TextInput::make('size')->label(__('Size'))->nullable()->columnSpan(2),
                TextInput::make('weight')
                    ->label(__('Weight per Item'))
                    ->numeric()
                    ->required()
                    ->step(0.0001)
                    ->minValue(0)
                    ->default(1)
                    ->columnSpan(2),
                Select::make('weight_unit')
                    ->label(__('Weight Unit'))
                    ->options(collect(WeightUnit::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                    ->required()
                    ->default(WeightUnit::TroyOunce->value)
                    ->columnSpan(2),
                Select::make('fineness')
                    ->label(__('Fineness'))
                    ->options(collect(Fineness::cases())->mapWithKeys(fn ($c) => [$c->value => $c->value])->toArray())
                    ->searchable()
                    ->nullable()
                    ->columnSpan(2),
                TextInput::make('mintage')
                    ->label(__('Mintage'))
                    ->numeric()
                    ->nullable()
                    ->placeholder(__('How many pieces were produced?'))
                    ->columnSpan(4),
                TextInput::make('face_value')
                    ->label(__('Face Value'))
                    ->numeric()
                    ->nullable()
                    ->placeholder(__('Nominal value printed on the item'))
                    ->columnSpan(2),
                Select::make('face_value_currency')
                    ->label(__('Face Value Currency'))
                    ->options(FaceValueCurrency::toArray())
                    ->searchable()
                    ->nullable()
                    ->columnSpan(2),
            ])->columns(8)->columnSpanFull(),

            Section::make(__('Look'))->schema([
                FileUpload::make('front_image_path')
                    ->label(__('Front (image side)'))
                    ->image()
                    ->directory('product-templates')
                    ->disk('public')
                    ->nullable()
                    ->columnSpan(4),
                FileUpload::make('back_image_path')
                    ->label(__('Back (value side)'))
                    ->image()
                    ->directory('product-templates')
                    ->disk('public')
                    ->nullable()
                    ->columnSpan(4),
                TextInput::make('text_front')
                    ->label(__('Text Front'))
                    ->nullable()
                    ->maxLength(255)
                    ->columnSpan(4),
                TextInput::make('text_back')
                    ->label(__('Text Back'))
                    ->nullable()
                    ->maxLength(255)
                    ->columnSpan(4),
                Repeater::make('assets')
                    ->label(__('Additional Media'))
                    ->addActionLabel(__('Add media'))
                    ->table([
                        TableColumn::make(__('Type'))->width('12rem'),
                        TableColumn::make(__('Label')),
                        TableColumn::make(__('File')),
                    ])
                    ->schema([
                        Select::make('type')
                            ->label(__('Type'))
                            ->options([
                                'image' => __('Image'),
                                'video' => __('Video'),
                            ])
                            ->default('image')
                            ->required(),
                        TextInput::make('label')->label(__('Label'))->nullable(),
                        FileUpload::make('path')
                            ->label(__('File'))
                            ->directory('product-templates/media')
                            ->disk('public')
                            ->required(),
                    ])
                    ->reorderable()
                    ->collapsible()
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ])->columns(8)->columnSpanFull(),

            Section::make(__('Notes'))->schema([
                Textarea::make('notes')->label(__('Notes'))->nullable()->columnSpanFull(),
            ])->columns(8)->columnSpanFull(),
        ])->columns(8);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            IconColumn::make('type')
                ->label(__('Type'))
                ->icon(fn (ProductType $state) => $state->icon())
                ->sortable()
                ->tooltip(fn (ProductType $state) => $state->label())
                ->toggleable(),
            TextColumn::make('material')
                ->label(__('Material'))
                ->formatStateUsing(fn (Material $state) => $state->symbol())
                ->sortable()
                ->badge()
                ->icon(fn (Material $state) => $state->icon())
                ->color(fn (Material $state) => $state->color())
                ->tooltip(fn (Material $state) => $state->label())
                ->toggleable(),
            TextColumn::make('name')->label(__('Name'))->searchable(),
            TextColumn::make('year')->label(__('Year'))->sortable()->toggleable(),
            TextColumn::make('manufacturer')
                ->label(__('Manufacturer'))
                ->formatStateUsing(fn (?string $state): ?string => $state === null
                    ? null
                    : (Manufacturer::tryFrom($state)?->label() ?? $state))
                ->sortable()
                ->searchable()
                ->toggleable(),
        ])->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductTemplates::route('/'),
            'create' => CreateProductTemplate::route('/create'),
            'edit' => EditProductTemplate::route('/{record}/edit'),
        ];
    }
}
