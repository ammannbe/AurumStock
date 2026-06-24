<?php

/** @var TestCase $this */

use App\Models\User;
use Tests\TestCase;

test('legacy confirm password screen is not available', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/confirm-password');

    $response->assertNotFound();
});

test('legacy confirm password endpoint is not available', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/confirm-password', [
        'password' => 'password',
    ]);

    $response->assertNotFound();
});

test('legacy confirm password endpoint with invalid password is not available', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/confirm-password', [
        'password' => 'wrong-password',
    ]);

    $response->assertNotFound();
});
