<?php

/** @var TestCase $this */

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

test('legacy reset password link screen is not available', function () {
    $response = $this->get('/forgot-password');

    $response->assertNotFound();
});

test('legacy reset password link endpoint is not available', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->post('/forgot-password', ['email' => $user->email]);

    $response->assertNotFound();
    Notification::assertNothingSent();
});

test('legacy reset password screen is not available', function () {
    $response = $this->get('/reset-password/fake-token');

    $response->assertNotFound();
});

test('legacy password reset endpoint is not available', function () {
    $user = User::factory()->create();

    $response = $this->post('/reset-password', [
        'token' => 'fake-token',
        'email' => $user->email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
});
