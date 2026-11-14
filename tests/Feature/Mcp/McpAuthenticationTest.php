<?php

use App\Filament\Pages\User\ConnectedAiClients;
use App\Mcp\McpPermission;
use App\Models\McpGrant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Livewire\Livewire;

const MCP_REDIRECT_URI = 'http://localhost:33418/callback';

/**
 * @return array<string, mixed>
 */
function mcpInitializePayload(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'pest', 'version' => '1.0'],
        ],
    ];
}

/**
 * Authorization request parameters with PKCE, which public clients must use.
 *
 * @return array<string, string>
 */
function mcpAuthorizeQuery(Client $client): array
{
    return [
        'client_id' => (string) $client->getKey(),
        'redirect_uri' => MCP_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => Registrar::OAUTH_SCOPE,
        'state' => 'xyz',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', str_repeat('v', 64), true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ];
}

beforeEach(function () {
    usePassportTestKeys();
});

it('lets clients register themselves and discover the server', function () {
    $this->postJson('/oauth/register', ['client_name' => 'Codex', 'redirect_uris' => [MCP_REDIRECT_URI]])
        ->assertCreated()
        ->assertJsonPath('scope', Registrar::OAUTH_SCOPE);

    $this->getJson('/.well-known/oauth-protected-resource/mcp/aurum')
        ->assertOk()
        ->assertJsonFragment(['resource' => url('/mcp/aurum')]);

    $this->postJson('/mcp/aurum', mcpInitializePayload())
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate');
});

it('sends guests to the login before the approval screen', function () {
    $client = mcpClient();

    $this->get(route('passport.authorizations.authorize', mcpAuthorizeQuery($client)))->assertRedirect('/app/login');
});

it('stores the chosen permissions on approval and issues a code', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('passport.authorizations.authorize', mcpAuthorizeQuery(mcpClient())))
        ->assertOk()
        ->assertSee('value="receipts"', false)
        ->assertSee('value="replace_media"', false)
        ->assertDontSee('value="replace_media" checked', false)
        ->assertSee('localhost')
        ->assertSee(route('mcp.authorize.approve'), false);

    $response = $this->post(route('mcp.authorize.approve'), ['auth_token' => session('authToken'), 'permissions' => ['read', 'create']]);

    expect((string) $response->headers->get('Location'))->toStartWith(MCP_REDIRECT_URI.'?code=')
        ->and(McpGrant::query()->where('user_id', $user->id)->sole()->permissions)->toBe(['read', 'create']);
});

it('requires at least one permission on approval', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('passport.authorizations.authorize', mcpAuthorizeQuery(mcpClient())))->assertOk();

    $this->post(route('mcp.authorize.approve'), ['auth_token' => session('authToken'), 'permissions' => []])
        ->assertSessionHasErrors('permissions');

    expect(McpGrant::query()->count())->toBe(0);
});

it('refuses tokens without a grant', function () {
    Passport::actingAs(User::factory()->create(), [Registrar::OAUTH_SCOPE], 'api', mcpClient());

    $this->postJson('/mcp/aurum', mcpInitializePayload())->assertForbidden()->assertJson(['error' => 'access_denied']);
});

it('lists only the permitted tools and records the last use', function () {
    $user = User::factory()->create();
    mcpConnect($user, [McpPermission::Read]);

    $this->postJson('/mcp/aurum', mcpInitializePayload())
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'AurumStock');

    $response = $this->postJson('/mcp/aurum', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], ['MCP-Protocol-Version' => '2025-06-18'])
        ->assertOk();

    $tools = array_column((array) $response->json('result.tools'), 'name');

    expect($tools)->toContain('list-orders', 'get-order', 'search-products', 'list-suppliers', 'list-depositories', 'search-product-templates');
    expect($tools)->not->toContain('create-order', 'create-product', 'create-supplier', 'attach-order-receipt');
    expect(McpGrant::query()->sole()->last_used_at)->not->toBeNull();
});

it('lists all tools with all permissions and marks read tools as read-only', function () {
    mcpConnect(User::factory()->create());

    $response = $this->postJson('/mcp/aurum', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], ['MCP-Protocol-Version' => '2025-06-18'])
        ->assertOk();

    $tools = collect((array) $response->json('result.tools'));

    expect($tools->pluck('name')->all())->toContain('create-order', 'create-product', 'create-supplier', 'attach-order-receipt')
        ->and($tools->firstWhere('name', 'search-products')['annotations']['readOnlyHint'] ?? null)->toBeTrue();
});

it('rate limits the endpoint', function () {
    mcpConnect(User::factory()->create());

    for ($i = 0; $i < 60; $i++) {
        $this->postJson('/mcp/aurum', mcpInitializePayload())->assertOk();
    }

    $this->postJson('/mcp/aurum', mcpInitializePayload())->assertTooManyRequests();
});

it('lets users change and revoke connected clients', function () {
    $user = User::factory()->create();
    $client = mcpClient();
    $grant = McpGrant::query()->create(['user_id' => $user->id, 'oauth_client_id' => $client->getKey(), 'permissions' => ['read']]);
    $foreign = McpGrant::query()->create(['user_id' => User::factory()->create()->id, 'oauth_client_id' => mcpClient('Fremd')->getKey(), 'permissions' => ['read']]);

    DB::table('oauth_access_tokens')->insert([
        'id' => 'token-1', 'user_id' => $user->id, 'client_id' => $client->getKey(), 'scopes' => '["mcp:use"]',
        'revoked' => false, 'created_at' => now(), 'updated_at' => now(), 'expires_at' => now()->addHour(),
    ]);

    $this->actingAs($user);

    Livewire::test(ConnectedAiClients::class)
        ->assertCanSeeTableRecords([$grant])
        ->assertCanNotSeeTableRecords([$foreign])
        ->assertSee(url('/mcp/aurum'))
        ->callTableAction('permissions', $grant, ['permissions' => ['read', 'receipts']])
        ->assertHasNoTableActionErrors();

    expect($grant->refresh()->permissions)->toBe(['read', 'receipts']);

    Livewire::test(ConnectedAiClients::class)->callTableAction('revoke', $grant);

    expect(McpGrant::query()->find($grant->id))->toBeNull()
        ->and((bool) DB::table('oauth_access_tokens')->where('id', 'token-1')->value('revoked'))->toBeTrue()
        ->and(McpGrant::query()->find($foreign->id))->not->toBeNull();
});
