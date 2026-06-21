<?php

namespace App\Filament\Resources;

use App\Enums\Currency;
use App\Enums\Fineness;
use App\Enums\Manufacturer;
use App\Enums\Material;
use App\Enums\Packaging;
use App\Enums\ProductType;
use App\Enums\Quality;
use App\Enums\WeightUnit;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Depository;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Average;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Products';

    protected static \UnitEnum|string|null $navigationGroup = 'Management';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            // Identification / ownership
            Hidden::make('user_id')->default(fn () => auth()->id()),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('depository_id')
                ->label('Depository')
                ->options(fn () => Depository::where('user_id', auth()->id())->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->default(fn () => Depository::where('user_id', auth()->id())->first()?->id),
            Select::make('type')
                ->options(collect(ProductType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->name])->toArray())
                ->required()
                ->default(ProductType::Coin->value),
            Select::make('material')
                ->options(collect(Material::cases())->mapWithKeys(fn ($c) => [$c->value => $c->name])->toArray())
                ->required()
                ->default(Material::Gold->value),
            TextInput::make('reference_number')->nullable()->label('Reference Number'),
            TextInput::make('year')->numeric()->nullable()->label('Year'),

            // Images & text
            FileUpload::make('front_image_path')
                ->label('Front Image')
                ->image()
                ->directory('products')
                ->disk('public')
                ->nullable(),
            FileUpload::make('back_image_path')
                ->label('Back Image')
                ->image()
                ->directory('products')
                ->disk('public')
                ->nullable(),
            TextInput::make('text_front')
                ->label('Text Front')
                ->nullable()
                ->maxLength(255),
            TextInput::make('text_back')
                ->label('Text Back')
                ->nullable()
                ->maxLength(255),

            // Physical attributes
            TextInput::make('weight')
                ->numeric()
                ->required()
                ->default(1)
                ->minValue(0)
                ->step(0.01),
            Select::make('weight_unit')
                ->options(collect(WeightUnit::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                ->required()
                ->default(WeightUnit::TroyOunce->value),
            Select::make('fineness')
                ->label('Fineness')
                ->options(collect(Fineness::cases())->mapWithKeys(fn ($c) => [$c->value => $c->value])->toArray())
                ->searchable()
                ->nullable(),
            TextInput::make('size')->nullable()->label('Size'),
            TextInput::make('amount')->numeric()->required()->default(1)->minValue(1),
            TextInput::make('face_value')->nullable()->label('Face Value'),

            // Provenance / metadata
            Select::make('quality')
                ->label('Quality')
                ->options(collect(Quality::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                ->searchable()
                ->nullable(),
            Checkbox::make('certificate')
                ->label('Certificate')
                ->inline(false),
            Select::make('manufacturer')
                ->label('Manufacturer')
                ->options(collect(Manufacturer::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                ->searchable()
                ->nullable(),
            Select::make('country_of_origin')
                ->label('Country of Origin')
                ->options([
                    'CH' => 'Switzerland (CH)',
                    'DE' => 'Germany (DE)',
                    'AT' => 'Austria (AT)',
                    'GB' => 'United Kingdom (GB)',
                    'US' => 'United States (US)',
                    'CA' => 'Canada (CA)',
                    'AU' => 'Australia (AU)',
                    'ZA' => 'South Africa (ZA)',
                    'FR' => 'France (FR)',
                    'RU' => 'Russia (RU)',
                    'CN' => 'China (CN)',
                    'MX' => 'Mexico (MX)',
                ])
                ->searchable()
                ->nullable(),
            Select::make('packaging')
                ->label('Packaging')
                ->options(Packaging::toArray())
                ->searchable()
                ->nullable(),
            TextInput::make('mintage')
                ->label('Mintage')
                ->numeric()
                ->nullable()
                ->minValue(0),

            // Financial
            TextInput::make('price')
                ->numeric()
                ->nullable()
                ->minValue(0)
                ->step(0.01)
                ->label('Purchase Price'),
            Select::make('price_currency')
                ->label('Purchase Price Currency')
                ->options(Currency::toArray())
                ->searchable()
                ->nullable(),
            DatePicker::make('purchase_date')
                ->label('Purchase Date')
                ->nullable(),
            DatePicker::make('taxed_date')
                ->label('Taxed Date')
                ->nullable(),

            // Notes
            Textarea::make('notes')->nullable()->columnSpan('full'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->label('Name'),
            TextColumn::make('front_image_preview')
                ->label('Image')
                ->html()
                ->getStateUsing(fn ($record) => (function ($record) {
                    $front = $record->front_image_path ? Storage::disk('public')->url($record->front_image_path) : null;
                    $back = $record->back_image_path ? Storage::disk('public')->url($record->back_image_path) : null;

                    $src = $front ?? $back;

                    if (! $src) {
                        return '';
                    }

                    $frontEsc = e($front);
                    $backEsc = e($back);
                    $srcEsc = e($src);

                    $imgStyle = 'height: 3rem; width: 3rem; object-fit: contain; border-radius: 0.375rem; cursor: pointer; margin-right: 0.5rem;';

                    $dataBackAttr = $back ? " data-back=\"{$backEsc}\"" : '';
                    $dataFrontAttr = $front ? " data-front=\"{$frontEsc}\"" : '';

                    $altFallback = $front && $back ? $front.'||'.$back : ($front ?? $back);
                    $altEsc = e($altFallback);
                    $imgHtml = "<img class=\"product-preview\" src=\"{$srcEsc}\" alt=\"{$altEsc}\"{$dataFrontAttr}{$dataBackAttr} style=\"{$imgStyle}\" />";

                    return $imgHtml;
                })($record)
                ),

            TextColumn::make('material')
                ->badge()
                ->formatStateUsing(fn (Material $state) => $state->symbol())
                ->icon(fn (Material $state) => $state->icon())
                ->color(fn (Material $state) => $state->color())
                ->tooltip(fn (Material $state) => $state->name),
            TextColumn::make('weight')
                ->label('Weight')
                ->getStateUsing(fn (Product $record) => weight($record->weight, $record->weight_unit->symbol())),
            TextColumn::make('weight_g')
                ->label('Weight (g)')
                ->getStateUsing(fn (Product $record) => weight($record->weight_unit->toGram($record->weight), $record->weight_unit->symbol()))
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('weight_oz')
                ->label('Weight (oz)')
                ->getStateUsing(fn (Product $record) => weight($record->weight_unit->toTroyOunce($record->weight), $record->weight_unit->symbol()))
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('amount')->label('Amount')->sortable(),
            TextColumn::make('depository.name')->label('Depository')->sortable(),
            TextColumn::make('price')->label('Price')->money(fn (Product $record) => $record->price_currency),
            TextColumn::make('material_value')->label('Material Value')->money('USD')->sortable(),
            TextColumn::make('year')->label('Year')->sortable(),

            TextColumn::make('text_front')->label('Text Front')->limit(30)->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('text_back')->label('Text Back')->limit(30)->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('quality')
                ->label('Quality')
                ->getStateUsing(fn ($record) => $record->quality ? Quality::from($record->quality)->label() : '-')
                ->toggleable(isToggledHiddenByDefault: true),
            IconColumn::make('certificate')
                ->label('Certificate')
                ->boolean()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('manufacturer')
                ->label('Manufacturer')
                ->getStateUsing(fn ($record) => $record->manufacturer ? Manufacturer::from($record->manufacturer)->label() : '-')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('country_of_origin')->label('Country')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('fineness')->label('Fineness')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('packaging')
                ->label('Packaging')
                ->getStateUsing(fn ($record) => $record->packaging ? Packaging::from($record->packaging)->label() : '-')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('mintage')->label('Mintage')->numeric()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('purchase_date')->label('Purchase Date')->date()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('taxed_date')->label('Taxed Date')->date()->toggleable(isToggledHiddenByDefault: true),

            IconColumn::make('notes')
                ->label('Notes')
                ->getStateUsing(fn ($record) => (bool) $record->notes)
                ->boolean()
                ->trueIcon('heroicon-o-document-text')
                ->falseIcon('heroicon-o-minus')
                ->tooltip(fn ($record) => $record->notes ?? '')
                ->toggleable(isToggledHiddenByDefault: true),

        ])->recordActions([
            EditAction::make()->color('secondary'),
            DeleteAction::make(),
        ])->toolbarActions([
            DeleteBulkAction::make(),
        ])->filters([
            SelectFilter::make('material')->options(collect(Material::cases())->mapWithKeys(fn ($c) => [$c->value => $c->name])->toArray()),
            SelectFilter::make('type')->options(collect(ProductType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->name])->toArray()),
            SelectFilter::make('weight_unit')->options(collect(WeightUnit::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray()),
            SelectFilter::make('depository_id')->label('Depository')->options(fn () => Depository::where('user_id', auth()->id())->pluck('name', 'id')),
            SelectFilter::make('year')->options(fn () => Product::query()->distinct()->pluck('year', 'year')->filter()->toArray()),
            SelectFilter::make('price_currency')->label('Currency')->options(fn () => Product::query()->distinct()->pluck('price_currency', 'price_currency')->filter()->toArray()),
        ])->defaultSort('name');
    }

    public static function getModelLabel(): string
    {
        return 'Product';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
