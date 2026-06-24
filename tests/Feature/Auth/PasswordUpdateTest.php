<?php

/** @var TestCase $this */

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

test('legacy password update endpoint is not available', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/app')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response->assertNotFound();

    $this->assertTrue(Hash::check('password', $user->refresh()->password));
});

test('legacy password update endpoint with invalid current password is not available', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/app')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response->assertNotFound();

    $this->assertTrue(Hash::check('password', $user->refresh()->password));
});
