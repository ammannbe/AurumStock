<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\CurrencyConverter;
use App\Support\MediaTypes;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = null;

    protected static \UnitEnum|string|null $navigationGroup = null;

    public static function getNavigationLabel(): string
    {
        return __('Orders');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Management');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(static::formFields())->columns(2);
    }

    /**
     * Shared with the inline "create order" form on the product resource.
     *
     * @return array<int, Component>
     */
    public static function formFields(): array
    {
        return [
            Hidden::make('user_id')->default(fn () => Auth::id()),
            TextInput::make('order_number')
                ->label(__('Order Number'))
                ->required()
                ->maxLength(255),
            Select::make('supplier_id')
                ->label(__('Supplier'))
                ->options(fn () => SupplierResource::optionsForCurrentUser())
                ->searchable()
                ->nullable()
                ->createOptionForm(SupplierResource::formFields())
                ->createOptionUsing(fn (array $data) => Supplier::create([
                    ...$data,
                    'user_id' => Auth::id(),
                ])->getKey()),
            DatePicker::make('order_date')
                ->label(__('Order Date'))
                ->nullable(),
            Repeater::make('receipts')
                ->label(__('Receipts'))
                ->addActionLabel(__('Add receipt'))
                ->schema([
                    FileUpload::make('path')
                        ->label(__('File'))
                        ->directory(Order::RECEIPT_DIRECTORY)
                        ->disk(Order::RECEIPT_DISK)
                        ->visibility('private')
                        ->acceptedFileTypes(MediaTypes::forReceipts())
                        ->required(),
                    TextInput::make('label')
                        ->placeholder(__('Label'))
                        ->hiddenLabel()
                        ->nullable(),
                ])
                ->extraItemActions([
                    Action::make('previewReceipt')
                        ->label(__('Preview'))
                        ->icon('heroicon-m-eye')
                        // Only saved files have a stored path to stream.
                        ->visible(fn (Repeater $component, array $arguments): bool => static::savedReceiptIndex($component, $arguments) !== null)
                        ->modalHeading(__('Receipt'))
                        ->modalContent(function (Repeater $component, array $arguments): ?View {
                            $index = static::savedReceiptIndex($component, $arguments);

                            /** @var Order $order */
                            $order = $component->getRecord();

                            return $index === null ? null : view('filament.orders.receipt-preview', [
                                'receipts' => [$order->previewableReceipts()[$index]],
                            ]);
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel(__('Close')),
                ])
                ->reorderable()
                ->collapsible()
                ->defaultItems(0)
                ->columnSpanFull(),
        ];
    }

    /**
     * Maps a repeater item to its index in the persisted receipts, or null when
     * the item has not been saved yet.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected static function savedReceiptIndex(Repeater $component, array $arguments): ?int
    {
        $record = $component->getRecord();

        if (! $record instanceof Order) {
            return null;
        }

        $itemKey = $arguments['item'] ?? null;

        if (! is_string($itemKey)) {
            return null;
        }

        $path = $component->getRawItemState($itemKey)['path'] ?? null;

        if (! is_string($path) || $path === '') {
            return null;
        }

        foreach ($record->receiptEntries() as $index => $entry) {
            if ($entry['path'] === $path) {
                return $index;
            }
        }

        return null;
    }

    public static function getEloquentQuery(): Builder
    {
        $userId = Auth::id();

        $query = parent::getEloquentQuery()->withCount('products');

        if (! $userId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('user_id', $userId);
    }

    /**
     * @return array<int, string>
     */
    public static function optionsForCurrentUser(): array
    {
        $userId = Auth::id();

        if (! $userId) {
            return [];
        }

        return Order::query()
            ->with('supplier')
            ->where('user_id', $userId)
            ->orderByDesc('order_date')
            ->orderBy('order_number')
            ->get()
            ->mapWithKeys(fn (Order $order): array => [$order->getKey() => static::optionLabel($order)])
            ->all();
    }

    public static function optionLabel(Order $order): string
    {
        $supplier = $order->supplier?->name;

        return $supplier ? $order->order_number.' — '.$supplier : $order->order_number;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('order_number')->label(__('Order Number'))->searchable()->sortable(),
            TextColumn::make('supplier.name')->label(__('Supplier'))->searchable()->sortable(),
            TextColumn::make('order_date')->label(__('Order Date'))->date()->sortable(),
            TextColumn::make('products_count')->label(__('Products'))->sortable(),
            TextColumn::make('receipts_count')
                ->label(__('Receipts'))
                ->getStateUsing(fn (Order $record) => count($record->receiptEntries())),
            TextColumn::make('total_price')
                ->label(__('Order Total'))
                ->getStateUsing(fn (Order $record) => static::orderTotalInPreferredCurrency($record))
                ->money(fn () => CurrencyConverter::preferredCode()),
        ])->filters([
            SelectFilter::make('supplier_id')
                ->label(__('Supplier'))
                ->options(fn () => SupplierResource::optionsForCurrentUser()),
        ])->recordActions([
            EditAction::make()->color('secondary'),
            DeleteAction::make(),
        ])->toolbarActions([
            DeleteBulkAction::make(),
        ])->defaultSort('order_date', 'desc');
    }

    /**
     * Sums the order's products, normalising their individual currencies into
     * the user's preferred one.
     */
    protected static function orderTotalInPreferredCurrency(Order $order): float
    {
        $totalUsd = $order->products->sum(function (Model $product): float {
            /** @var Product $product */
            $price = (float) ($product->price ?? 0);

            if ($price === 0.0) {
                return 0.0;
            }

            $rate = CurrencyConverter::rate($product->price_currency);

            return ($price / $rate) * ($product->amount ?? 1);
        });

        return CurrencyConverter::fromUsd($totalUsd);
    }

    public static function getModelLabel(): string
    {
        return __('Order');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Orders');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
