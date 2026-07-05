<?php

namespace App\Filament\Resources;

use App\Enums\SharePermission;
use App\Filament\Resources\DepositoryResource\Pages\CreateDepository;
use App\Filament\Resources\DepositoryResource\Pages\EditDepository;
use App\Filament\Resources\DepositoryResource\Pages\ListDepositories;
use App\Models\Depository;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DepositoryResource extends Resource
{
    protected static ?string $model = Depository::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = null;

    protected static \UnitEnum|string|null $navigationGroup = null;

    public static function getNavigationLabel(): string
    {
        return __('Depositories');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Management');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Hidden::make('user_id')->default(fn () => Auth::id()),
            TextInput::make('name')->label(__('Name'))->required()->maxLength(255)->columnSpanFull()->maxWidth('xl'),
            Textarea::make('description')->label(__('Description'))->nullable()->columnSpanFull()->maxWidth('xl'),
        ]);
    }

    public static function makeEditSharesAction(): Action
    {
        return Action::make('editShares')
            ->label(__('Edit Shares'))
            ->icon('heroicon-o-user-group')
            ->color('gray')
            ->visible(fn (Depository $record): bool => $record->user_id === Auth::id())
            ->fillForm(function (Depository $record): array {
                $permissionsByUser = $record->shares()->pluck('permission', 'shared_with_user_id');

                return [
                    'shares' => $permissionsByUser
                        ->map(fn (SharePermission $permission, $sharedWithUserId): array => [
                            'shared_with_user_id' => (int) $sharedWithUserId,
                            'permission' => $permission->value,
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
            ->action(function (Depository $record, array $data): void {
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

    public static function getEloquentQuery(): Builder
    {
        $userId = Auth::id();

        $query = parent::getEloquentQuery()->withCount('shares');

        if (! $userId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $visibilityQuery) use ($userId): void {
            $visibilityQuery
                ->where('user_id', $userId)
                ->orWhereHas('shares', fn (Builder $shareQuery) => $shareQuery->where('shared_with_user_id', $userId));
        });
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('Name'))->searchable(),
            TextColumn::make('description')->label(__('Description'))->limit(50),
            TextColumn::make('shares_count')
                ->label(__('Shares'))
                ->badge()
                ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray'),
        ])->recordActions([
            static::makeEditSharesAction(),
            EditAction::make()->color('secondary'),
            DeleteAction::make(),
        ])->toolbarActions([
            DeleteBulkAction::make(),
        ])->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDepositories::route('/'),
            'create' => CreateDepository::route('/create'),
            'edit' => EditDepository::route('/{record}/edit'),
        ];
    }
}
