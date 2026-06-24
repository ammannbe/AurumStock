<?php

namespace App\Filament\Pages\User;

use App\Enums\Currency;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class Profile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationLabel = null;

    protected static \UnitEnum|string|null $navigationGroup = null;

    protected static ?string $slug = 'profile';

    protected string $view = 'filament.pages.user.profile';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('Profile');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Account');
    }

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $preferredCurrency = $user->getAttribute('preferred_currency');

        if ($preferredCurrency instanceof Currency) {
            $preferredCurrency = $preferredCurrency->value;
        }

        if (! is_string($preferredCurrency) || $preferredCurrency === '') {
            $preferredCurrency = Currency::USD->value;
        }

        $preferredLocale = $user->preferredLocale();

        $this->getSchema('form')?->fill([
            'name' => $user->name,
            'email' => $user->email,
            'notes' => $user->notes,
            'preferred_currency' => $preferredCurrency,
            'preferred_locale' => $preferredLocale,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->required()
                    ->email()
                    ->maxLength(255)
                    ->rules([
                        Rule::unique(User::class, 'email')->ignore(Auth::id()),
                    ]),
                Select::make('preferred_currency')
                    ->label(__('Preferred Currency'))
                    ->options(Currency::toArray())
                    ->searchable()
                    ->required(),
                Select::make('preferred_locale')
                    ->label(__('Language'))
                    ->options([
                        'en' => __('English'),
                        'de' => __('German'),
                    ])
                    ->required(),
                Textarea::make('notes')
                    ->rows(4)
                    ->maxLength(5000),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var array{name: string, email: string, preferred_currency: string, preferred_locale: string, notes: ?string} $state */
        $state = $this->getSchema('form')?->getState() ?? [];

        $user->update($state);

        Notification::make()
            ->title(__('Profile updated'))
            ->success()
            ->send();
    }
}
