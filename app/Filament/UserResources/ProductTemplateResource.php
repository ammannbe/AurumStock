<?php

namespace App\Filament\UserResources;

use App\Enums\Currency;
use App\Enums\Manufacturer;
use App\Enums\Material;
use App\Enums\Packaging;
use App\Enums\ProductType;
use App\Enums\Quality;
use App\Enums\WeightUnit;
use App\Filament\UserResources\ProductTemplateResource\Pages\CreateProductTemplate;
use App\Filament\UserResources\ProductTemplateResource\Pages\EditProductTemplate;
use App\Filament\UserResources\ProductTemplateResource\Pages\ListProductTemplates;
use App\Models\ProductTemplate;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
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
                TextInput::make('name')->required()->maxLength(255)->columnSpanFull(),
                Select::make('material')
                    ->options(collect(Material::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                    ->required()
                    ->default(Material::Gold->value)
                    ->columnSpan(4),
                Select::make('type')
                    ->options(collect(ProductType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                    ->required()
                    ->default(ProductType::Coin->value)
                    ->columnSpan(4),
                TextInput::make('year')->numeric()->nullable()->columnSpan(2),
                Select::make('manufacturer')
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
                TextInput::make('size')->nullable()->columnSpan(2),
                TextInput::make('weight')
                    ->label(__('Weight per Item'))
                    ->numeric()
                    ->required()
                    ->step(0.0001)
                    ->minValue(0)
                    ->columnSpan(2),
                Select::make('weight_unit')
                    ->options(collect(WeightUnit::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                    ->required()
                    ->columnSpan(2),
                TextInput::make('amount')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->minValue(1)
                    ->columnSpan(2),
                TextInput::make('fineness')
                    ->nullable()
                    ->columnSpan(4),
                TextInput::make('mintage')->numeric()->nullable()->columnSpan(4),
                TextInput::make('face_value')->numeric()->nullable()->columnSpan(2),
                TextInput::make('reference_number')->nullable()->columnSpan(2),
                Select::make('quality')
                    ->options(collect(Quality::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                    ->searchable()
                    ->nullable()
                    ->columnSpan(4),
                Checkbox::make('certificate')->inline(false)->columnSpan(2),
                Select::make('packaging')
                    ->options(collect(Packaging::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                    ->searchable()
                    ->nullable()
                    ->columnSpan(2),
                TextInput::make('price')
                    ->numeric()
                    ->nullable()
                    ->minValue(0)
                    ->step(0.01)
                    ->label(__('Total Purchase Price'))
                    ->columnSpan(2),
                Select::make('price_currency')
                    ->options(Currency::toArray())
                    ->searchable()
                    ->nullable()
                    ->columnSpan(2),
                DatePicker::make('purchase_date')->nullable()->columnSpan(4),
                TextInput::make('tax_year')
                    ->numeric()
                    ->minValue(1900)
                    ->maxValue(2100)
                    ->nullable()
                    ->columnSpan(4),
            ])->columns(8)->columnSpanFull(),

            Section::make(__('Look'))->schema([
                FileUpload::make('front_image_path')
                    ->label(__('Front Image'))
                    ->image()
                    ->directory('product-templates')
                    ->disk('public')
                    ->nullable()
                    ->columnSpan(4),
                FileUpload::make('back_image_path')
                    ->label(__('Back Image'))
                    ->image()
                    ->directory('product-templates')
                    ->disk('public')
                    ->nullable()
                    ->columnSpan(4),
                Repeater::make('assets')
                    ->label(__('Additional Media'))
                    ->schema([
                        Select::make('type')
                            ->options([
                                'image' => __('Image'),
                                'video' => __('Video'),
                            ])
                            ->required(),
                        TextInput::make('label')->nullable(),
                        FileUpload::make('path')
                            ->label(__('File'))
                            ->directory('product-templates/media')
                            ->disk('public')
                            ->required(),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->columnSpanFull(),
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
            ])->columns(8)->columnSpanFull(),

            Section::make(__('Notes'))->schema([
                Textarea::make('notes')->nullable()->columnSpanFull(),
            ])->columns(8)->columnSpanFull(),
        ])->columns(8);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            IconColumn::make('type')
                ->icon(fn (ProductType $state) => $state->icon())
                ->sortable()
                ->tooltip(fn (ProductType $state) => $state->label())
                ->toggleable(),
            TextColumn::make('material')
                ->formatStateUsing(fn (Material $state) => $state->symbol())
                ->sortable()
                ->badge()
                ->icon(fn (Material $state) => $state->icon())
                ->color(fn (Material $state) => $state->color())
                ->tooltip(fn (Material $state) => $state->label())
                ->toggleable(),
            TextColumn::make('name')->searchable(),
            TextColumn::make('year')->sortable()->toggleable(),
            TextColumn::make('manufacturer')->sortable()->searchable()->toggleable(),
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
