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
use App\Models\Exchange;
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
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    /** @var array<string, float> */
    protected static array $exchangeRateCache = [];

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = null;

    protected static \UnitEnum|string|null $navigationGroup = null;

    public static function getNavigationLabel(): string
    {
        return __('Products');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Management');
    }

    protected static function preferredCurrencyCode(): string
    {
        $preferredCurrency = Auth::user()?->preferred_currency;

        if ($preferredCurrency instanceof Currency) {
            return $preferredCurrency->value;
        }

        return Currency::USD->value;
    }

    protected static function preferredCurrencyRate(): float
    {
        $currency = static::preferredCurrencyCode();

        if ($currency === Currency::USD->value) {
            return 1.0;
        }

        if (array_key_exists($currency, static::$exchangeRateCache)) {
            return static::$exchangeRateCache[$currency];
        }

        $rate = (float) (Exchange::query()->where('currency', $currency)->value('value') ?? 0);

        return static::$exchangeRateCache[$currency] = $rate > 0 ? $rate : 1.0;
    }

    protected static function convertUsdToPreferred(float|int|null $amount): float
    {
        return (float) ($amount ?? 0) * static::preferredCurrencyRate();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            // Identification / ownership
            Hidden::make('user_id')->default(fn () => auth()->id()),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('depository_id')
                ->label(__('Depository'))
                ->options(fn () => Depository::where('user_id', auth()->id())->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->default(fn () => Depository::where('user_id', auth()->id())->first()?->id),
            Select::make('type')
                ->options(collect(ProductType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                ->required()
                ->default(ProductType::Coin->value),
            Select::make('material')
                ->options(collect(Material::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                ->required()
                ->default(Material::Gold->value),
            TextInput::make('reference_number')->nullable()->label(__('Reference Number')),
            TextInput::make('year')->numeric()->nullable()->label(__('Year')),

            // Images & text
            FileUpload::make('front_image_path')
                ->label(__('Front Image'))
                ->image()
                ->directory('products')
                ->disk('public')
                ->nullable(),
            FileUpload::make('back_image_path')
                ->label(__('Back Image'))
                ->image()
                ->directory('products')
                ->disk('public')
                ->nullable(),
            TextInput::make('text_front')
                ->label(__('Text Front'))
                ->nullable()
                ->maxLength(255),
            TextInput::make('text_back')
                ->label(__('Text Back'))
                ->nullable()
                ->maxLength(255),

            // Physical attributes
            TextInput::make('weight')
                ->label(__('Weight per Item'))
                ->numeric()
                ->required()
                ->default(1)
                ->minValue(0)
                ->step(0.0001),
            Select::make('weight_unit')
                ->options(collect(WeightUnit::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                ->required()
                ->default(WeightUnit::TroyOunce->value),
            Select::make('fineness')
                ->label(__('Fineness'))
                ->options(collect(Fineness::cases())->mapWithKeys(fn ($c) => [$c->value => $c->value])->toArray())
                ->searchable()
                ->nullable(),
            TextInput::make('size')->nullable()->label(__('Size')),
            TextInput::make('amount')->label(__('Amount'))->numeric()->required()->default(1)->minValue(1),
            TextInput::make('face_value')->nullable()->label(__('Face Value')),

            // Provenance / metadata
            Select::make('quality')
                ->label(__('Quality'))
                ->options(collect(Quality::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                ->searchable()
                ->nullable(),
            Checkbox::make('certificate')
                ->label(__('Certificate'))
                ->inline(false),
            Select::make('manufacturer')
                ->label(__('Manufacturer'))
                ->options(collect(Manufacturer::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                ->searchable()
                ->nullable(),
            Select::make('country_of_origin')
                ->label(__('Country of Origin'))
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
                ])
                ->searchable()
                ->nullable(),
            Select::make('packaging')
                ->label(__('Packaging'))
                ->options(Packaging::toArray())
                ->searchable()
                ->nullable(),
            TextInput::make('mintage')
                ->label(__('Mintage'))
                ->numeric()
                ->nullable()
                ->minValue(0),

            // Financial
            TextInput::make('price')
                ->numeric()
                ->nullable()
                ->minValue(0)
                ->step(0.01)
                ->label(__('Total Purchase Price')),
            Select::make('price_currency')
                ->label(__('Purchase Currency'))
                ->options(Currency::toArray())
                ->searchable()
                ->nullable(),
            DatePicker::make('purchase_date')
                ->label(__('Purchase Date'))
                ->nullable(),
            DatePicker::make('taxed_date')
                ->label(__('Taxed Date'))
                ->nullable(),

            // Notes
            Textarea::make('notes')->nullable()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('type')
                ->label(__('Type'))
                ->formatStateUsing(fn ($state) => ProductType::from((int) $state)->label())
                ->sortable()
                ->toggleable(),
            TextColumn::make('name')->searchable()->label(__('Name')),
            TextColumn::make('front_image_preview')
                ->label(__('Image'))
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
                })($record))
                ->toggleable(),

            TextColumn::make('material')
                ->formatStateUsing(fn (Material $state) => $state->symbol())
                ->sortable()
                ->badge()
                ->icon(fn (Material $state) => $state->icon())
                ->color(fn (Material $state) => $state->color())
                ->tooltip(fn (Material $state) => $state->label())
                ->toggleable(),
            TextColumn::make('weight')
                ->label(__('Weight'))
                ->numeric()
                ->suffix(fn (Product $record) => ' '.$record->weight_unit->symbol())
                ->toggleable(),
            TextColumn::make('weight_total')
                ->label(__('Total Weight'))
                ->numeric()
                ->suffix(fn (Product $record) => ' '.$record->weight_unit->symbol())
                ->toggleable()
                ->summarize([
                    Summarizer::make()
                        ->label(Material::Gold->label())
                        ->numeric()
                        ->suffix(' '.WeightUnit::Gram->symbol())
                        ->query(fn (Builder $query) => $query->where('material', Material::Gold->value))
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();
                            if (empty($ids)) {
                                return 0;
                            }
                            $products = Product::whereIn('id', $ids)->get();

                            return $products->sum(function (Product $product) {
                                return $product->weight_unit->toGram($product->weight) * $product->amount;
                            });
                        }),
                    Summarizer::make()
                        ->label(Material::Silver->label())
                        ->numeric()
                        ->suffix(' '.WeightUnit::Gram->symbol())
                        ->query(fn (Builder $query) => $query->where('material', Material::Silver->value))
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();
                            if (empty($ids)) {
                                return 0;
                            }
                            $products = Product::whereIn('id', $ids)->get();

                            return $products->sum(function (Product $product) {
                                return $product->weight_unit->toGram($product->weight) * $product->amount;
                            });
                        }),
                    Summarizer::make()
                        ->label(Material::Platinum->label())
                        ->numeric()
                        ->suffix(' '.WeightUnit::Gram->symbol())
                        ->query(fn (Builder $query) => $query->where('material', Material::Platinum->value))
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();
                            if (empty($ids)) {
                                return 0;
                            }
                            $products = Product::whereIn('id', $ids)->get();

                            return $products->sum(function (Product $product) {
                                return $product->weight_unit->toGram($product->weight) * $product->amount;
                            });
                        }),
                    Summarizer::make()
                        ->label(Material::Palladium->label())
                        ->numeric()
                        ->suffix(' '.WeightUnit::Gram->symbol())
                        ->query(fn (Builder $query) => $query->where('material', Material::Palladium->value))
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();
                            if (empty($ids)) {
                                return 0;
                            }
                            $products = Product::whereIn('id', $ids)->get();

                            return $products->sum(function (Product $product) {
                                return $product->weight_unit->toGram($product->weight) * $product->amount;
                            });
                        }),
                    Summarizer::make()
                        ->label(__('Total'))
                        ->numeric()
                        ->suffix(' '.WeightUnit::Gram->symbol())
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();

                            if (empty($ids)) {
                                return 0;
                            }

                            $products = Product::whereIn('id', $ids)->get();

                            return $products->sum(fn (Product $p) => $p->weight_unit->toGram($p->weight) * $p->amount);
                        }),
                ]),
            TextColumn::make('amount')
                ->label(__('Amount'))
                ->numeric()
                ->toggleable()
                ->summarize([
                    Sum::make()
                        ->label(Material::Gold->label())
                        ->numeric()
                        ->query(fn (Builder $query) => $query->where('material', Material::Gold->value)),
                    Sum::make()
                        ->label(Material::Silver->label())
                        ->numeric()
                        ->query(fn (Builder $query) => $query->where('material', Material::Silver->value)),
                    Sum::make()
                        ->label(Material::Platinum->label())
                        ->numeric()
                        ->query(fn (Builder $query) => $query->where('material', Material::Platinum->value)),
                    Sum::make()
                        ->label(Material::Palladium->label())
                        ->numeric()
                        ->query(fn (Builder $query) => $query->where('material', Material::Palladium->value)),
                    Sum::make()
                        ->label(__('Total'))
                        ->numeric(),
                ])
                ->sortable(),
            TextColumn::make('depository.name')->label(__('Depository'))->sortable()->toggleable(),
            TextColumn::make('price')
                ->label(__('Purchase Price'))
                ->money(fn (Product $record) => $record->price_currency)
                ->toggleable()
                ->summarize([
                    Summarizer::make()
                        ->label(Material::Gold->label())
                        ->numeric()
                        ->money(fn () => static::preferredCurrencyCode())
                        ->query(fn (Builder $query) => $query->where('material', Material::Gold->value))
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();
                            if (empty($ids)) {
                                return 0;
                            }
                            $products = Product::with(['exchange', 'stockPrice'])->whereIn('id', $ids)->get();
                            $totalUsd = $products->sum(function (Product $p) {
                                $price = $p->price ?? 0;
                                $exchangeValue = (float) ($p->exchange?->getAttribute('value') ?? 0);
                                $priceInUSD = ($price && $exchangeValue > 0) ? $price / $exchangeValue : 0;

                                return $priceInUSD * ($p->amount ?? 1);
                            });

                            return static::convertUsdToPreferred($totalUsd);
                        }),
                    Summarizer::make()
                        ->label(Material::Silver->label())
                        ->numeric()
                        ->money(fn () => static::preferredCurrencyCode())
                        ->query(fn (Builder $query) => $query->where('material', Material::Silver->value))
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();
                            if (empty($ids)) {
                                return 0;
                            }
                            $products = Product::with(['exchange', 'stockPrice'])->whereIn('id', $ids)->get();
                            $totalUsd = $products->sum(function (Product $p) {
                                $price = $p->price ?? 0;
                                $exchangeValue = (float) ($p->exchange?->getAttribute('value') ?? 0);
                                $priceInUSD = ($price && $exchangeValue > 0) ? $price / $exchangeValue : 0;

                                return $priceInUSD * ($p->amount ?? 1);
                            });

                            return static::convertUsdToPreferred($totalUsd);
                        }),
                    Summarizer::make()
                        ->label(Material::Platinum->label())
                        ->numeric()
                        ->money(fn () => static::preferredCurrencyCode())
                        ->query(fn (Builder $query) => $query->where('material', Material::Platinum->value))
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();
                            if (empty($ids)) {
                                return 0;
                            }
                            $products = Product::with(['exchange', 'stockPrice'])->whereIn('id', $ids)->get();
                            $totalUsd = $products->sum(function (Product $p) {
                                $price = $p->price ?? 0;
                                $exchangeValue = (float) ($p->exchange?->getAttribute('value') ?? 0);
                                $priceInUSD = ($price && $exchangeValue > 0) ? $price / $exchangeValue : 0;

                                return $priceInUSD * ($p->amount ?? 1);
                            });

                            return static::convertUsdToPreferred($totalUsd);
                        }),
                    Summarizer::make()
                        ->label(Material::Palladium->label())
                        ->numeric()
                        ->money(fn () => static::preferredCurrencyCode())
                        ->query(fn (Builder $query) => $query->where('material', Material::Palladium->value))
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();
                            if (empty($ids)) {
                                return 0;
                            }
                            $products = Product::with(['exchange', 'stockPrice'])->whereIn('id', $ids)->get();
                            $totalUsd = $products->sum(function (Product $p) {
                                $price = $p->price ?? 0;
                                $exchangeValue = (float) ($p->exchange?->getAttribute('value') ?? 0);
                                $priceInUSD = ($price && $exchangeValue > 0) ? $price / $exchangeValue : 0;

                                return $priceInUSD * ($p->amount ?? 1);
                            });

                            return static::convertUsdToPreferred($totalUsd);
                        }),
                    Summarizer::make()
                        ->label(__('Total'))
                        ->numeric()
                        ->money(fn () => static::preferredCurrencyCode())
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();
                            if (empty($ids)) {
                                return 0;
                            }
                            $products = Product::with(['exchange', 'stockPrice'])->whereIn('id', $ids)->get();
                            $totalUsd = $products->sum(function (Product $p) {
                                $price = $p->price ?? 0;
                                $exchangeValue = (float) ($p->exchange?->getAttribute('value') ?? 0);
                                $priceInUSD = ($price && $exchangeValue > 0) ? $price / $exchangeValue : 0;

                                return $priceInUSD * ($p->amount ?? 1);
                            });

                            return static::convertUsdToPreferred($totalUsd);
                        }),
                ]),
            TextColumn::make('material_value_total')
                ->label(__('Material Value'))
                ->getStateUsing(fn (Product $record) => static::convertUsdToPreferred($record->material_value_total))
                ->money(fn () => static::preferredCurrencyCode())
                ->sortable()
                ->toggleable()
                ->summarize([
                    Summarizer::make()
                        ->label(Material::Gold->label())
                        ->numeric()
                        ->money(fn () => static::preferredCurrencyCode())
                        ->query(fn (Builder $query) => $query->where('material', Material::Gold->value))
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();
                            if (empty($ids)) {
                                return 0;
                            }
                            $products = Product::with(['exchange', 'stockPrice'])->whereIn('id', $ids)->get();
                            $totalMaterialUsd = $products->sum(function (Product $p) {
                                return $p->material_value_total ?? 0;
                            });

                            return static::convertUsdToPreferred($totalMaterialUsd);
                        }),
                    Summarizer::make()
                        ->label(Material::Silver->label())
                        ->numeric()
                        ->money(fn () => static::preferredCurrencyCode())
                        ->query(fn (Builder $query) => $query->where('material', Material::Silver->value))
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();
                            if (empty($ids)) {
                                return 0;
                            }
                            $products = Product::with(['exchange', 'stockPrice'])->whereIn('id', $ids)->get();
                            $totalMaterialUsd = $products->sum(function (Product $p) {
                                return $p->material_value_total ?? 0;
                            });

                            return static::convertUsdToPreferred($totalMaterialUsd);
                        }),
                    Summarizer::make()
                        ->label(Material::Platinum->label())
                        ->numeric()
                        ->money(fn () => static::preferredCurrencyCode())
                        ->query(fn (Builder $query) => $query->where('material', Material::Platinum->value))
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();
                            if (empty($ids)) {
                                return 0;
                            }
                            $products = Product::with(['exchange', 'stockPrice'])->whereIn('id', $ids)->get();
                            $totalMaterialUsd = $products->sum(function (Product $p) {
                                return $p->material_value_total ?? 0;
                            });

                            return static::convertUsdToPreferred($totalMaterialUsd);
                        }),
                    Summarizer::make()
                        ->label(Material::Palladium->label())
                        ->numeric()
                        ->money(fn () => static::preferredCurrencyCode())
                        ->query(fn (Builder $query) => $query->where('material', Material::Palladium->value))
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();
                            if (empty($ids)) {
                                return 0;
                            }
                            $products = Product::with(['exchange', 'stockPrice'])->whereIn('id', $ids)->get();
                            $totalMaterialUsd = $products->sum(function (Product $p) {
                                return $p->material_value_total ?? 0;
                            });

                            return static::convertUsdToPreferred($totalMaterialUsd);
                        }),
                    Summarizer::make()
                        ->label(__('Total'))
                        ->numeric()
                        ->money(fn () => static::preferredCurrencyCode())
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();

                            if (empty($ids)) {
                                return 0;
                            }

                            $products = Product::with(['exchange', 'stockPrice'])
                                ->whereIn('id', $ids)
                                ->get();

                            $totalMaterialUsd = $products->sum(function (Product $p) {
                                return $p->material_value_total ?? 0;
                            });

                            return static::convertUsdToPreferred($totalMaterialUsd);
                        }),
                ]),
            TextColumn::make('price_trend')
                ->label(__('Price Trend'))
                ->numeric(decimalPlaces: 0)
                ->suffix(' %')
                ->toggleable()
                ->summarize([
                    Summarizer::make()
                        ->label(Material::Gold->label())
                        ->numeric()
                        ->suffix(' %')
                        ->query(fn (Builder $query) => $query->where('material', Material::Gold->value))
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();
                            if (empty($ids)) {
                                return 0;
                            }

                            $products = Product::with(['exchange', 'stockPrice'])->whereIn('id', $ids)->get();
                            $totalPurchaseUsd = $products->sum(function (Product $p) {
                                $price = $p->price ?? 0;
                                $exchangeValue = (float) ($p->exchange?->getAttribute('value') ?? 0);
                                $priceInUSD = ($price && $exchangeValue > 0) ? $price / $exchangeValue : 0;

                                return $priceInUSD * ($p->amount ?? 1);
                            });
                            $totalMaterialUsd = $products->sum(function (Product $p) {
                                return ($p->material_value_total ?? 0) * ($p->amount ?? 1);
                            });

                            if ($totalPurchaseUsd <= 0) {
                                return 0;
                            }

                            return ($totalMaterialUsd - $totalPurchaseUsd) / $totalPurchaseUsd * 100;
                        }),
                    Summarizer::make()
                        ->label(Material::Silver->label())
                        ->numeric()
                        ->suffix(' %')
                        ->query(fn (Builder $query) => $query->where('material', Material::Silver->value))
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();
                            if (empty($ids)) {
                                return 0;
                            }

                            $products = Product::with(['exchange', 'stockPrice'])->whereIn('id', $ids)->get();
                            $totalPurchaseUsd = $products->sum(function (Product $p) {
                                $price = $p->price ?? 0;
                                $exchangeValue = (float) ($p->exchange?->getAttribute('value') ?? 0);
                                $priceInUSD = ($price && $exchangeValue > 0) ? $price / $exchangeValue : 0;

                                return $priceInUSD * ($p->amount ?? 1);
                            });
                            $totalMaterialUsd = $products->sum(function (Product $p) {
                                return ($p->material_value_total ?? 0) * ($p->amount ?? 1);
                            });

                            if ($totalPurchaseUsd <= 0) {
                                return 0;
                            }

                            return ($totalMaterialUsd - $totalPurchaseUsd) / $totalPurchaseUsd * 100;
                        }),
                    Summarizer::make()
                        ->label(Material::Platinum->label())
                        ->numeric()
                        ->suffix(' %')
                        ->query(fn (Builder $query) => $query->where('material', Material::Platinum->value))
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();
                            if (empty($ids)) {
                                return 0;
                            }

                            $products = Product::with(['exchange', 'stockPrice'])->whereIn('id', $ids)->get();
                            $totalPurchaseUsd = $products->sum(function (Product $p) {
                                $price = $p->price ?? 0;
                                $exchangeValue = (float) ($p->exchange?->getAttribute('value') ?? 0);
                                $priceInUSD = ($price && $exchangeValue > 0) ? $price / $exchangeValue : 0;

                                return $priceInUSD * ($p->amount ?? 1);
                            });
                            $totalMaterialUsd = $products->sum(function (Product $p) {
                                return ($p->material_value_total ?? 0) * ($p->amount ?? 1);
                            });

                            if ($totalPurchaseUsd <= 0) {
                                return 0;
                            }

                            return ($totalMaterialUsd - $totalPurchaseUsd) / $totalPurchaseUsd * 100;
                        }),
                    Summarizer::make()
                        ->label(Material::Palladium->label())
                        ->numeric()
                        ->suffix(' %')
                        ->query(fn (Builder $query) => $query->where('material', Material::Palladium->value))
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();
                            if (empty($ids)) {
                                return 0;
                            }

                            $products = Product::with(['exchange', 'stockPrice'])->whereIn('id', $ids)->get();
                            $totalPurchaseUsd = $products->sum(function (Product $p) {
                                $price = $p->price ?? 0;
                                $exchangeValue = (float) ($p->exchange?->getAttribute('value') ?? 0);
                                $priceInUSD = ($price && $exchangeValue > 0) ? $price / $exchangeValue : 0;

                                return $priceInUSD * ($p->amount ?? 1);
                            });
                            $totalMaterialUsd = $products->sum(function (Product $p) {
                                return ($p->material_value_total ?? 0) * ($p->amount ?? 1);
                            });

                            if ($totalPurchaseUsd <= 0) {
                                return 0;
                            }

                            return ($totalMaterialUsd - $totalPurchaseUsd) / $totalPurchaseUsd * 100;
                        }),
                    Summarizer::make()
                        ->label(__('Total'))
                        ->numeric()
                        ->suffix(' %')
                        ->using(function (Builder $query) {
                            $ids = $query->pluck('id')->filter()->toArray();
                            if (empty($ids)) {
                                return 0;
                            }
                            $products = Product::with(['exchange', 'stockPrice'])->whereIn('id', $ids)->get();
                            $totalPurchaseUsd = $products->sum(function (Product $p) {
                                $price = $p->price ?? 0;
                                $exchangeValue = (float) ($p->exchange?->getAttribute('value') ?? 0);
                                $priceInUSD = ($price && $exchangeValue > 0) ? $price / $exchangeValue : 0;

                                return $priceInUSD;
                            });
                            $totalMaterialUsd = $products->sum(function (Product $p) {
                                return $p->material_value_total ?? 0;
                            });

                            if ($totalPurchaseUsd <= 0) {
                                return 0;
                            }

                            return ($totalMaterialUsd - $totalPurchaseUsd) / $totalPurchaseUsd * 100;
                        }),
                ]),
            TextColumn::make('year')->label(__('Year'))->sortable()->toggleable(),

            TextColumn::make('text_front')->label(__('Text Front'))->limit(30)->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('text_back')->label(__('Text Back'))->limit(30)->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('quality')
                ->label(__('Quality'))
                ->getStateUsing(fn ($record) => $record->quality ? Quality::from($record->quality)->label() : '-')
                ->toggleable(isToggledHiddenByDefault: true),
            IconColumn::make('certificate')
                ->label(__('Certificate'))
                ->boolean()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('manufacturer')
                ->label(__('Manufacturer'))
                ->getStateUsing(fn ($record) => $record->manufacturer ? Manufacturer::from($record->manufacturer)->label() : '-')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('country_of_origin')->label(__('Country'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('fineness')->label(__('Fineness'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('packaging')
                ->label(__('Packaging'))
                ->getStateUsing(fn ($record) => $record->packaging ? Packaging::from($record->packaging)->label() : '-')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('mintage')->label(__('Mintage'))->numeric()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('purchase_date')->label(__('Purchase Date'))->isoDate()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('taxed_date')->label(__('Taxed Date'))->isoDate()->toggleable(isToggledHiddenByDefault: true),

            IconColumn::make('notes')
                ->label(__('Notes'))
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
            SelectFilter::make('material')->options(collect(Material::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray()),
            SelectFilter::make('type')->options(collect(ProductType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray()),
            SelectFilter::make('depository_id')->label(__('Depository'))->options(fn () => Depository::where('user_id', auth()->id())->pluck('name', 'id')),
            SelectFilter::make('year')->options(fn () => Product::query()->distinct()->pluck('year', 'year')->filter()->toArray()),
            SelectFilter::make('price_currency')->label(__('Purchase Currency'))->options(fn () => Product::query()->distinct()->pluck('price_currency', 'price_currency')->filter()->toArray()),
        ])->defaultSort('name')->summaries(pageCondition: false);
    }

    public static function getModelLabel(): string
    {
        return __('Product');
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
