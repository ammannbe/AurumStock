<?php

use App\Models\User;

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

it('rejects requests without a token', function () {
    $this->postJson('/mcp/aurum', mcpInitializePayload())
        ->assertUnauthorized()
        ->assertJson(['error' => 'unauthenticated'])
        ->assertHeader('WWW-Authenticate');
});

it('rejects requests with a wrong token', function () {
    User::factory()->create()->issueMcpToken();

    $this->withToken('aurum_'.str_repeat('x', 48))
        ->postJson('/mcp/aurum', mcpInitializePayload())
        ->assertUnauthorized();

    $this->withToken('not-even-prefixed')
        ->postJson('/mcp/aurum', mcpInitializePayload())
        ->assertUnauthorized();
});

it('rejects a revoked token', function () {
    $user = User::factory()->create();
    $token = $user->issueMcpToken();
    $user->revokeMcpToken();

    $this->withToken($token)
        ->postJson('/mcp/aurum', mcpInitializePayload())
        ->assertUnauthorized();
});

it('accepts a valid token and lists the tools', function () {
    $token = User::factory()->create()->issueMcpToken();

    $this->withToken($token)
        ->postJson('/mcp/aurum', mcpInitializePayload())
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'AurumStock');

    $response = $this->withToken($token)
        ->postJson('/mcp/aurum', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], ['MCP-Protocol-Version' => '2025-06-18'])
        ->assertOk();

    $names = collect((array) $response->json('result.tools'))->pluck('name')->all();

    expect($names)->toContain(
        'list-suppliers', 'create-supplier', 'list-depositories', 'search-product-templates',
        'search-products', 'list-orders', 'get-order', 'create-order', 'create-product', 'attach-order-receipt',
    );

    $readOnly = collect((array) $response->json('result.tools'))->firstWhere('name', 'search-products');
    expect($readOnly['annotations']['readOnlyHint'] ?? null)->toBeTrue();
});

it('does not start a session', function () {
    $token = User::factory()->create()->issueMcpToken();

    $this->withToken($token)
        ->postJson('/mcp/aurum', mcpInitializePayload())
        ->assertOk()
        ->assertCookieMissing(config('session.cookie'));
});

it('rate limits the endpoint', function () {
    $token = User::factory()->create()->issueMcpToken();

    for ($i = 0; $i < 60; $i++) {
        $this->withToken($token)->postJson('/mcp/aurum', mcpInitializePayload())->assertOk();
    }

    $this->withToken($token)->postJson('/mcp/aurum', mcpInitializePayload())->assertTooManyRequests();
});

it('creates, rotates and revokes a token via artisan', function () {
    $user = User::factory()->create(['email' => 'anna@example.com']);

    $this->artisan('app:mcp-token', ['email' => 'anna@example.com'])
        ->expectsOutputToContain('MCP token for anna@example.com created.')
        ->assertSuccessful();

    $user->refresh();
    expect($user->mcp_token_hash)->toHaveLength(64)
        ->and($user->mcp_token_created_at)->not->toBeNull();

    $firstHash = $user->mcp_token_hash;

    $this->artisan('app:mcp-token', ['email' => 'anna@example.com'])
        ->expectsOutputToContain('previous token is no longer valid')
        ->assertSuccessful();

    expect($user->refresh()->mcp_token_hash)->not->toBe($firstHash);

    $this->artisan('app:mcp-token', ['email' => 'anna@example.com', '--revoke' => true])
        ->expectsOutputToContain('revoked')
        ->assertSuccessful();

    expect($user->refresh()->mcp_token_hash)->toBeNull();
});

it('prints a token that authenticates and stores only its hash', function () {
    $user = User::factory()->create();
    $token = $user->issueMcpToken();

    expect($token)->toStartWith('aurum_')->toHaveLength(54)
        ->and($user->refresh()->mcp_token_hash)->toBe(hash('sha256', $token))
        ->and(User::findByMcpToken($token)?->is($user))->toBeTrue()
        ->and($user->toArray())->not->toHaveKey('mcp_token_hash');
});

it('fails for an unknown email', function () {
    $this->artisan('app:mcp-token', ['email' => 'nobody@example.com'])->assertFailed();
});
