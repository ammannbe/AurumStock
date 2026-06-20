<?php

namespace App\Filament\Resources;

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
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
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
            Hidden::make('user_id')->default(fn () => auth()->id()),
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
            Select::make('price_currency')
                ->label('Price currency')
                ->options([
                    'USD' => 'USD',
                    'CHF' => 'CHF',
                    'EUR' => 'EUR',
                ])
                ->searchable()
                ->nullable(),
            TextInput::make('year')->numeric()->nullable(),
            TextInput::make('reference_number')->nullable(),
            TextInput::make('size')->nullable(),
            FileUpload::make('front_image_path')
                ->label('Front image')
                ->image()
                ->directory('products')
                ->disk('public')
                ->nullable(),
            FileUpload::make('back_image_path')
                ->label('Back image')
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
            Select::make('quality')
                ->label('Qualität')
                ->options(collect(Quality::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                ->searchable()
                ->nullable(),
            Checkbox::make('certificate')
                ->label('Zertifikat'),
            Select::make('manufacturer')
                ->label('Hersteller')
                ->options(collect(Manufacturer::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                ->searchable()
                ->nullable(),
            Select::make('country_of_origin')
                ->label('Herkunftsland')
                ->options([
                    'CH' => 'Schweiz (CH)',
                    'DE' => 'Deutschland (DE)',
                    'AT' => 'Österreich (AT)',
                    'GB' => 'Vereinigtes Königreich (GB)',
                    'US' => 'Vereinigte Staaten (US)',
                    'CA' => 'Kanada (CA)',
                    'AU' => 'Australien (AU)',
                    'ZA' => 'Südafrika (ZA)',
                    'FR' => 'Frankreich (FR)',
                    'RU' => 'Russland (RU)',
                    'CN' => 'China (CN)',
                    'MX' => 'Mexiko (MX)',
                ])
                ->searchable()
                ->nullable(),
            Select::make('fineness')
                ->label('Feinheit')
                ->options(collect(Fineness::cases())->mapWithKeys(fn ($c) => [$c->value => $c->value])->toArray())
                ->searchable()
                ->nullable(),
            Select::make('packaging')
                ->label('Verpackung')
                ->options(collect(Packaging::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                ->searchable()
                ->nullable(),
            TextInput::make('mintage')
                ->label('Auflage')
                ->numeric()
                ->nullable(),
            DatePicker::make('purchase_date')
                ->label('Kaufdatum')
                ->nullable(),
            Textarea::make('notes')->nullable()->columnSpan('full'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
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

                    $imgStyle = 'height:3rem;width:3rem;object-fit:cover;border-radius:0.375rem;cursor:pointer;margin-right:0.5rem';

                    $onHover = '(function(el){ if(el.dataset.back){ el.dataset.orig=el.src; el.src=el.dataset.back; } })(this)';
                    $onOut = '(function(el){ if(el.dataset.orig){ el.src=el.dataset.orig; } })(this)';

                    $overlayJs = "(function(front, back){\n  var id='__img-overlay';\n  var existing=document.getElementById(id);\n  if(existing){ existing.remove(); return; }\n  var overlay=document.createElement('div');\n  overlay.id=id;\n  overlay.style.position='fixed'; overlay.style.left=0; overlay.style.top=0; overlay.style.right=0; overlay.style.bottom=0; overlay.style.background='rgba(0,0,0,0.75)'; overlay.style.display='flex'; overlay.style.alignItems='center'; overlay.style.justifyContent='center'; overlay.style.zIndex=9999;\n  var container=document.createElement('div'); container.style.position='relative'; container.style.display='flex'; container.style.gap='1rem'; container.style.maxWidth='95%'; container.style.maxHeight='95%'; container.style.padding='1rem'; container.style.alignItems='center';\n  var img1=document.createElement('img'); img1.src=front; img1.style.maxWidth='90vw'; img1.style.maxHeight='90vh'; img1.style.objectFit='contain';\n  container.appendChild(img1);\n  if(back){ var img2=document.createElement('img'); img2.src=back; img2.style.maxWidth='90vw'; img2.style.maxHeight='90vh'; img2.style.objectFit='contain'; container.appendChild(img2); }\n  var btn=document.createElement('button'); btn.innerText='x'; btn.style.position='absolute'; btn.style.top='0.5rem'; btn.style.right='0.5rem'; btn.style.padding='0.5rem 0.75rem'; btn.style.background='white'; btn.onclick=function(){ overlay.remove(); };\n  overlay.appendChild(container); overlay.appendChild(btn); document.body.appendChild(overlay);\n})";

                    // Build the img tag with data-back when available
                    // Img with data attributes; attach a single initializer script to enable
                    // hover swap and click overlay without inline event handlers.
                    $dataBackAttr = $back ? " data-back=\"{$backEsc}\"" : '';
                    $dataFrontAttr = $front ? " data-front=\"{$frontEsc}\"" : '';

                    // Prevent default navigation on click (anchor wrapper) but allow event to bubble
                    // Pack front/back into alt as a fallback if data-* attrs are stripped.
                    $altFallback = $front && $back ? $front.'||'.$back : ($front ?? $back);
                    $altEsc = e($altFallback);
                    $imgHtml = "<img class=\"product-preview\" src=\"{$srcEsc}\" alt=\"{$altEsc}\"{$dataFrontAttr}{$dataBackAttr} style=\"{$imgStyle}\" />";

                    // We rely on the registered public JS `product-previews.js`
                    // to provide hover and overlay/slider behavior.
                    return $imgHtml;
                })($record)
                ),
            TextColumn::make('text_front')->label('Text Front')->limit(30)->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('text_back')->label('Text Back')->limit(30)->toggleable(isToggledHiddenByDefault: true),
            BadgeColumn::make('material')->getStateUsing(fn ($record) => Material::from($record->material)->name),
            TextColumn::make('quality')
                ->label('Qualität')
                ->getStateUsing(fn ($record) => $record->quality ? Quality::from($record->quality)->label() : '-')
                ->toggleable(isToggledHiddenByDefault: true),
            IconColumn::make('certificate')
                ->label('Zertifikat')
                ->boolean()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('manufacturer')
                ->label('Hersteller')
                ->getStateUsing(fn ($record) => $record->manufacturer ? Manufacturer::from($record->manufacturer)->label() : '-')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('country_of_origin')->label('Herkunftsland')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('fineness')->label('Feinheit')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('packaging')
                ->label('Verpackung')
                ->getStateUsing(fn ($record) => $record->packaging ? Packaging::from($record->packaging)->label() : '-')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('mintage')->label('Auflage')->numeric()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('purchase_date')->label('Kaufdatum')->date()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('weight'),
            TextColumn::make('amount'),
            TextColumn::make('depository.name')->label('Depository')->sortable(),
            TextColumn::make('raw_material_value')->label('Raw USD')->getStateUsing(fn ($record) => $record->rawMaterialValueUsd() ? number_format($record->rawMaterialValueUsd(), 2) : '-'),
            TextColumn::make('price')->label('Price'),
            TextColumn::make('year')->sortable(),
        ])->actions([
            EditAction::make()->color('secondary'),
            DeleteAction::make(),
        ])->bulkActions([
            DeleteBulkAction::make(),
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

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
