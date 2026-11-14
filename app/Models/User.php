<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Currency;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * @method static \Database\Factories\UserFactory factory(...$parameters)
 */
#[Fillable(['name', 'email', 'password', 'notes', 'two_factor_enabled', 'passkey_enabled', 'is_admin', 'preferred_currency', 'preferred_locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasLocalePreference, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_enabled' => 'boolean',
        'passkey_enabled' => 'boolean',
        'is_admin' => 'boolean',
        'preferred_currency' => Currency::class,
    ];

    public function preferredLocale(): string
    {
        $locale = $this->getAttribute('preferred_locale');

        if (is_string($locale) && $locale !== '') {
            return $locale;
        }

        return config('app.locale', 'en');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function depositories(): HasMany
    {
        return $this->hasMany(Depository::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return (bool) $this->is_admin;
        }

        return $panel->getId() === 'user';
    }
}
