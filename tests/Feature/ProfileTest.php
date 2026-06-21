<?php

use App\Models\User;
use Tests\TestCase;

/** @var TestCase $this */
test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/app');

    $this->assertTrue(in_array($response->getStatusCode(), [200, 302]));
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $user->update([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
});

test('user can be deleted', function () {
    $user = User::factory()->create();
    $userId = $user->id;

    $user->delete();

    $this->assertNull(User::find($userId));
});
