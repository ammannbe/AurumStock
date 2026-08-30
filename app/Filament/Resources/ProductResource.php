<?php

namespace App\Filament\Resources;

use App\Enums\Currency;
use App\Enums\FaceValueCurrency;
use App\Enums\Fineness;
use App\Enums\Manufacturer;
use App\Enums\Material;
use App\Enums\Packaging;
use App\Enums\ProductType;
use App\Enums\Quality;
use App\Enums\SharePermission;
use App\Enums\WeightUnit;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Depository;
use App\Models\Product;
use App\Models\ProductTemplate;
use App\Models\User;
use App\Support\CurrencyConverter;
use App\Support\MediaTypes;
use App\Support\ProductSpreadsheetExporter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

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
        return CurrencyConverter::preferredCode();
    }

    protected static function preferredCurrencyRate(): float
    {
        return CurrencyConverter::rate();
    }

    protected static function convertUsdToPreferred(float|int|null $amount): float
    {
        return CurrencyConverter::fromUsd($amount);
    }

    protected static function exportFilteredRecords(ListRecords $livewire, string $format): BinaryFileResponse
    {
        return static::exportRecords($livewire->getFilteredSortedTableQuery()->get(), $format);
    }

    /**
     * @param  EloquentCollection<int, covariant \Illuminate\Database\Eloquent\Model>  $records
     */
    protected static function exportRecords(EloquentCollection $records, string $format): BinaryFileResponse
    {
        $records->load('depository');

        /** @var Collection<int, Product> $products */
        $products = collect($records->all());

        return ProductSpreadsheetExporter::download($products, $format);
    }

    /**
     * @return array<int, string>
     */
    protected static function depositoryOptionsForCurrentUser(): array
    {
        $userId = Auth::id();

        if (! $userId) {
            return [];
        }

        return Depository::query()
            ->where(function (EloquentBuilder $query) use ($userId): void {
                $query
                    ->where('user_id', $userId)
                    ->orWhereHas('shares', fn (EloquentBuilder $shareQuery) => $shareQuery
                        ->where('shared_with_user_id', $userId)
                        ->where('permission', SharePermission::Edit));
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function makeEditSharesAction(): Action
    {
        return Action::make('editShares')
            ->label(__('Edit Shares'))
            ->icon('heroicon-o-user-group')
            ->color('gray')
            ->visible(fn (Product $record): bool => $record->user_id === Auth::id())
            ->fillForm(function (Product $record): array {
                $permissionsByUser = $record->shares()->pluck('permission', 'shared_with_user_id');

                return [
                    'shares' => $permissionsByUser
                        ->map(fn ($permission, $sharedWithUserId): array => [
                            'shared_with_user_id' => (int) $sharedWithUserId,
                            'permission' => (int) $permission,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->schema([
                Repeater::make('shares')
                    ->label(__('Shares'))
                    ->addActionLabel(__('Add Share'))
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->schema([
                        Select::make('shared_with_user_id')
                            ->label(__('User'))
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => User::query()
                                ->whereKeyNot(Auth::id())
                                ->where('name', 'like', "%{$search}%")
                                ->orderBy('name')
                                ->limit(50)
                                ->pluck('name', 'id')
                                ->all())
                            ->getOptionLabelUsing(fn ($value): ?string => User::query()->find($value)?->name)
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        Select::make('permission')
                            ->label(__('Permission'))
                            ->required()
                            ->options([
                                SharePermission::View->value => SharePermission::View->label(),
                                SharePermission::Edit->value => SharePermission::Edit->label(),
                            ])
                            ->default(SharePermission::View->value),
                    ])
                    ->columns(2),
            ])
            ->action(function (Product $record, array $data): void {
                if ($record->user_id !== Auth::id()) {
                    return;
                }

                /** @var array<int, array{shared_with_user_id?: mixed, permission?: mixed}> $shareRows */
                $shareRows = is_array($data['shares'] ?? null) ? $data['shares'] : [];

                $shares = collect($shareRows)
                    ->filter(fn (array $row): bool => filled($row['shared_with_user_id'] ?? null))
                    ->map(fn (array $row): array => [
                        'shared_with_user_id' => (int) $row['shared_with_user_id'],
                        'permission' => (int) ($row['permission'] ?? SharePermission::View->value),
                    ])
                    ->reject(fn (array $row): bool => $row['shared_with_user_id'] === $record->user_id)
                    ->unique('shared_with_user_id')
                    ->values();

                $keepUserIds = $shares->pluck('shared_with_user_id')->all();

                if (empty($keepUserIds)) {
                    $record->shares()->delete();
                } else {
                    $record->shares()->whereNotIn('shared_with_user_id', $keepUserIds)->delete();
                }

                foreach ($shares as $share) {
                    $record->shares()->updateOrCreate(
                        ['shared_with_user_id' => $share['shared_with_user_id']],
                        [
                            'owner_user_id' => $record->user_id,
                            'permission' => $share['permission'],
                        ]
                    );
                }
            });
    }

    public static function getEloquentQuery(): EloquentBuilder
    {
        $userId = Auth::id();

        $query = parent::getEloquentQuery()->withCount('shares');

        if (! $userId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (EloquentBuilder $visibilityQuery) use ($userId): void {
            $visibilityQuery
                ->where('user_id', $userId)
                ->orWhereHas('shares', fn (EloquentBuilder $shareQuery) => $shareQuery->where('shared_with_user_id', $userId))
                ->orWhereHas('depository.shares', fn (EloquentBuilder $shareQuery) => $shareQuery->where('shared_with_user_id', $userId));
        });
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make(__('General'))->schema([
                Hidden::make('user_id')->default(fn () => Auth::id()),
                Select::make('template_id')
                    ->label(__('Vorlage aus Datenbank'))
                    ->options(fn () => ProductTemplate::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->nullable()
                    ->live()
                    ->afterStateUpdated(function (?int $state, Set $set): void {
                        if (! $state) {
                            return;
                        }

                        $template = ProductTemplate::query()->find($state);

                        if (! $template) {
                            return;
                        }

                        $set('name', $template->name);
                        $set('year', $template->year);
                        $set('material', $template->material);
                        $set('type', $template->type);
                        $set('manufacturer', $template->manufacturer);
                        $set('country_of_origin', $template->country_of_origin);
                        $set('size', $template->size);
                        $set('weight', $template->weight);
                        $set('weight_unit', $template->weight_unit);
                        $set('fineness', $template->fineness);
                        $set('mintage', $template->mintage);
                        $set('face_value', $template->face_value);
                        $set('face_value_currency', $template->face_value_currency);
                        $set('front_image_path', $template->front_image_path);
                        $set('back_image_path', $template->back_image_path);
                        $set('text_front', $template->text_front);
                        $set('text_back', $template->text_back);
                        $set('notes', $template->notes);
                    })
                    ->columnSpanFull(),
                TextInput::make('name')->required()->maxLength(255)->columnSpan(3),
                TextInput::make('year')->numeric()->nullable()->label(__('Year'))->columnSpan(1),

                Select::make('depository_id')
                    ->label(__('Depository'))
                    ->options(fn (): array => static::depositoryOptionsForCurrentUser())
                    ->searchable()
                    ->required()
                    ->default(fn () => array_key_first(static::depositoryOptionsForCurrentUser()))
                    ->columnSpan(4),
                Select::make('material')
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
                    ->nullable()->columnSpan(4),
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
                        'CK' => __('Cook Islands (CK)'),
                        'MN' => __('Mongolia (MN)'),
                        'NU' => __('Niue (NU)'),
                    ])
                    ->searchable()
                    ->nullable()->columnSpan(4),
            ])->columns(8)->columnSpanFull(),

            Section::make(__('Technical data'))->schema([
                TextInput::make('size')->nullable()->label(__('Size'))->columnSpan(2),
                TextInput::make('amount')
                    ->label(__('Amount'))
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->minValue(1)
                    ->columnSpan(2),
                TextInput::make('weight')
                    ->label(__('Weight per Item'))
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->minValue(0)
                    ->step(0.0001)
                    ->columnSpan(2),
                Select::make('weight_unit')
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
                Select::make('quality')
                    ->label(__('Quality'))
                    ->options(collect(Quality::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                    ->searchable()
                    ->nullable()
                    ->columnSpan(2),
                Checkbox::make('certificate')
                    ->label(__('Certificate'))
                    ->inline(false)
                    ->columnSpan(2),
                TextInput::make('reference_number')
                    ->nullable()
                    ->label(__('Reference Number'))
                    ->columnSpan(2),
                Select::make('packaging')
                    ->label(__('Packaging'))
                    ->options(Packaging::toArray())
                    ->searchable()
                    ->nullable()
                    ->columnSpan(4),
                TextInput::make('mintage')
                    ->label(__('Mintage'))
                    ->placeholder(__('How many pieces were produced?'))
                    ->numeric()
                    ->nullable()
                    ->minValue(0)
                    ->columnSpan(4),
            ])->columns(8)->columnSpanFull(),

            Section::make(__('Financial'))->schema([
                TextInput::make('face_value')
                    ->numeric()
                    ->nullable()
                    ->label(__('Face Value'))
                    ->placeholder(__('Nominal value printed on the item'))
                    ->columnSpan(2),
                Select::make('face_value_currency')
                    ->label(__('Face Value Currency'))
                    ->options(FaceValueCurrency::toArray())
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
                    ->label(__('Purchase Currency'))
                    ->options(Currency::toArray())
                    ->searchable()
                    ->nullable()
                    ->default(fn (): string => CurrencyConverter::preferredCode())
                    ->columnSpan(2),

                DatePicker::make('purchase_date')
                    ->label(__('Purchase Date'))
                    ->nullable()
                    ->columnSpan(4),
                TextInput::make('tax_year')
                    ->label(__('Tax Year'))
                    ->numeric()
                    ->minValue(1900)
                    ->maxValue(2100)
                    ->nullable()
                    ->columnSpan(4),
            ])->columns(8)->columnSpanFull(),

            Section::make(__('Look'))->schema([
                FileUpload::make('front_image_path')
                    ->label(__('Front (image side)'))
                    ->image()
                    ->directory('products')
                    ->disk('public')
                    ->nullable()
                    ->columnSpan(4),
                FileUpload::make('back_image_path')
                    ->label(__('Back (value side)'))
                    ->image()
                    ->directory('products')
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
                            ->live()
                            ->required(),
                        TextInput::make('label')->label(__('Label'))->nullable(),
                        FileUpload::make('path')
                            ->label(__('File'))
                            ->directory('products/media')
                            ->disk('public')
                            ->acceptedFileTypes(fn (Get $get): array => MediaTypes::for($get('type')))
                            ->required(),
                    ])
                    ->reorderable()
                    ->collapsible()
                    ->defaultItems(0)
                    ->columnSpanFull(),
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
                ->label(__('Type'))
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
            TextColumn::make('name')->searchable()->label(__('Name')),
            TextColumn::make('shares_count')
                ->label(__('Shares'))
                ->badge()
                ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                ->toggleable(),
            TextColumn::make('template_sync')
                ->label(__('Template'))
                ->badge()
                ->getStateUsing(fn (Product $record): string => match (true) {
                    $record->template_id === null => __('No template'),
                    $record->isSynchronizedWithTemplate() => __('In sync'),
                    default => __('Outdated'),
                })
                ->color(fn (Product $record): string => match (true) {
                    $record->template_id === null => 'gray',
                    $record->isSynchronizedWithTemplate() => 'success',
                    default => 'warning',
                })
                ->icon(fn (Product $record): ?string => match (true) {
                    $record->template_id === null => null,
                    $record->isSynchronizedWithTemplate() => 'heroicon-m-check-badge',
                    default => 'heroicon-m-exclamation-triangle',
                })
                ->toggleable(isToggledHiddenByDefault: true),
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

                    $imgStyle = 'height: 3rem; min-height: 3rem; width: 3rem; min-width: 3rem; object-fit: contain; border-radius: 0.375rem; cursor: pointer; margin-right: 0.5rem; cursor: zoom-in;';

                    $dataBackAttr = $back ? " data-back=\"{$backEsc}\"" : '';
                    $dataFrontAttr = $front ? " data-front=\"{$frontEsc}\"" : '';

                    $altFallback = $front && $back ? $front.'||'.$back : ($front ?? $back);
                    $altEsc = e($altFallback);
                    $imgHtml = "<img class=\"product-preview\" src=\"{$srcEsc}\" alt=\"{$altEsc}\"{$dataFrontAttr}{$dataBackAttr} style=\"{$imgStyle}\" />";

                    return $imgHtml;
                })($record))
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

            TextColumn::make('text_front')->label(__('Text Front'))->limit(30)->toggleable(isToggledHiddenByDefault: true)->searchable(),
            TextColumn::make('text_back')->label(__('Text Back'))->limit(30)->toggleable(isToggledHiddenByDefault: true)->searchable(),
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
            TextColumn::make('purchase_date')->label(__('Purchase Date'))->isoDate()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('tax_year')->label(__('Tax Year'))->sortable()->toggleable(isToggledHiddenByDefault: true),

            IconColumn::make('notes')
                ->label(__('Notes'))
                ->getStateUsing(fn ($record) => (bool) $record->notes)
                ->boolean()
                ->trueIcon('heroicon-o-document-text')
                ->falseIcon('heroicon-o-minus')
                ->tooltip(fn ($record) => $record->notes ?? '')
                ->toggleable(isToggledHiddenByDefault: true),

        ])->recordActions([
            static::makeEditSharesAction(),
            EditAction::make()->color('secondary'),
            DeleteAction::make(),
        ])->toolbarActions([
            DeleteBulkAction::make(),
            BulkAction::make('export_csv')
                ->label(__('Export CSV'))
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (EloquentCollection $records) => static::exportRecords($records, 'csv')),
            BulkAction::make('export_ods')
                ->label(__('Export ODS'))
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (EloquentCollection $records) => static::exportRecords($records, 'ods')),
        ])->headerActions([
            Action::make('export_all_csv')
                ->label(__('Export CSV'))
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (ListRecords $livewire) => static::exportFilteredRecords($livewire, 'csv')),
            Action::make('export_all_ods')
                ->label(__('Export ODS'))
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (ListRecords $livewire) => static::exportFilteredRecords($livewire, 'ods')),
        ])->filters([
            SelectFilter::make('material')->options(collect(Material::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray()),
            SelectFilter::make('type')->options(collect(ProductType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray()),
            SelectFilter::make('depository_id')->label(__('Depository'))->options(fn (): array => static::depositoryOptionsForCurrentUser()),
            SelectFilter::make('year')->options(fn () => Product::query()->distinct()->pluck('year', 'year')->filter()->toArray()),
            SelectFilter::make('price_currency')->label(__('Purchase Currency'))->options(fn () => Product::query()->distinct()->pluck('price_currency', 'price_currency')->filter()->toArray()),
            SelectFilter::make('tax_year')
                ->label(__('Tax Year'))
                ->options(fn () => Product::query()->whereNotNull('tax_year')->distinct()->orderByDesc('tax_year')->pluck('tax_year', 'tax_year')->toArray()),
            Filter::make('purchase_date')
                ->schema([
                    DatePicker::make('purchase_date_from')->label(__('Purchase Date From')),
                    DatePicker::make('purchase_date_until')->label(__('Purchase Date Until')),
                ])
                ->query(fn (EloquentBuilder $query, array $data): EloquentBuilder => $query
                    ->when($data['purchase_date_from'] ?? null, fn (EloquentBuilder $q, $date) => $q->whereDate('purchase_date', '>=', $date))
                    ->when($data['purchase_date_until'] ?? null, fn (EloquentBuilder $q, $date) => $q->whereDate('purchase_date', '<=', $date)))
                ->indicateUsing(function (array $data): array {
                    $indicators = [];

                    if ($data['purchase_date_from'] ?? null) {
                        $indicators[] = __('Purchase Date From').': '.$data['purchase_date_from'];
                    }

                    if ($data['purchase_date_until'] ?? null) {
                        $indicators[] = __('Purchase Date Until').': '.$data['purchase_date_until'];
                    }

                    return $indicators;
                }),
        ])->defaultSort('name')->summaries(pageCondition: false);
    }

    public static function getModelLabel(): string
    {
        return __('Product');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Products');
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
