<?php

use App\Mcp\McpPermission;
use App\Models\McpGrant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something(): void
{
    // ..
}

/**
 * Passport needs a key pair to issue tokens; generated once per test run.
 */
function usePassportTestKeys(): void
{
    static $keys = null;

    if ($keys === null) {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        assert($key !== false);
        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        assert(is_array($details));
        $keys = ['private' => $privateKey, 'public' => $details['key']];
    }

    config(['passport.private_key' => $keys['private'], 'passport.public_key' => $keys['public']]);
}

function mcpClient(string $name = 'Claude Code'): Client
{
    return app(ClientRepository::class)
        ->createAuthorizationCodeGrantClient($name, ['http://localhost:33418/callback'], confidential: false);
}

/**
 * Connects an AI client for the user with the given permissions and acts as it (Passport token).
 *
 * @param  list<McpPermission>|null  $permissions  null = all permissions
 */
function mcpConnect(User $user, ?array $permissions = null): Client
{
    $client = mcpClient();

    McpGrant::query()->create([
        'user_id' => $user->id,
        'oauth_client_id' => $client->getKey(),
        'permissions' => array_map(
            fn (McpPermission $permission): string => $permission->value,
            $permissions ?? McpPermission::cases(),
        ),
    ]);

    Passport::actingAs($user, [Registrar::OAUTH_SCOPE], 'api', $client);

    return $client;
}
