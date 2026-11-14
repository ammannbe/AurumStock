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
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string|null $mcp_token_hash
 * @property Carbon|null $mcp_token_created_at
 *
 * @method static \Database\Factories\UserFactory factory(...$parameters)
 */
#[Fillable(['name', 'email', 'password', 'notes', 'two_factor_enabled', 'passkey_enabled', 'is_admin', 'preferred_currency', 'preferred_locale'])]
#[Hidden(['password', 'remember_token', 'mcp_token_hash'])]
class User extends Authenticatable implements FilamentUser, HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_enabled' => 'boolean',
        'passkey_enabled' => 'boolean',
        'is_admin' => 'boolean',
        'preferred_currency' => Currency::class,
        'mcp_token_created_at' => 'datetime',
    ];

    public const MCP_TOKEN_PREFIX = 'aurum_';

    /**
     * Generates a new personal MCP token, stores only its hash and returns
     * the plaintext, which cannot be recovered afterwards.
     */
    public function issueMcpToken(): string
    {
        $token = self::MCP_TOKEN_PREFIX.Str::random(48);

        $this->forceFill([
            'mcp_token_hash' => self::hashMcpToken($token),
            'mcp_token_created_at' => now(),
        ])->save();

        return $token;
    }

    public function revokeMcpToken(): void
    {
        $this->forceFill([
            'mcp_token_hash' => null,
            'mcp_token_created_at' => null,
        ])->save();
    }

    public static function hashMcpToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByMcpToken(string $token): ?self
    {
        if (! str_starts_with($token, self::MCP_TOKEN_PREFIX)) {
            return null;
        }

        $hash = self::hashMcpToken($token);

        $user = self::query()->where('mcp_token_hash', $hash)->first();

        if (! $user || ! is_string($user->mcp_token_hash) || ! hash_equals($user->mcp_token_hash, $hash)) {
            return null;
        }

        return $user;
    }

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
