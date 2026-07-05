<?php

namespace App\Filament\Pages\User;

use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ChangePassword extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-key';

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'change-password';

    protected string $view = 'filament.pages.user.change-password';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('Change Password');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Account');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('current_password')
                    ->label(__('Current Password'))
                    ->password()
                    ->revealable()
                    ->required(),
                TextInput::make('password')
                    ->label(__('New Password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8),
                TextInput::make('password_confirmation')
                    ->label(__('Confirm New Password'))
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var array<string, mixed> $state */
        $state = $this->getSchema('form')?->getState() ?? [];

        Validator::make($state, [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ])->validate();

        $user->update([
            'password' => (string) ($state['password'] ?? ''),
        ]);

        $this->getSchema('form')?->fill([]);

        Notification::make()
            ->title(__('Password updated'))
            ->success()
            ->send();

        $this->dispatch('password-updated-reload', url: static::getUrl());
    }
}
