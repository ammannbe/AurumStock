<?php

/** @var TestCase $this */

use App\Models\User;
use Tests\TestCase;

test('legacy email verification screen is not available', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get('/verify-email');

    $response->assertNotFound();
});

test('legacy email verification endpoint is not available', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get('/verify-email/'.$user->id.'/'.sha1($user->email));

    $response->assertNotFound();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('legacy email verification resend endpoint is not available', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->post('/email/verification-notification');

    $response->assertNotFound();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
