<?php

/** @var TestCase $this */

use App\Models\User;
use Tests\TestCase;

test('legacy laravel login route is not available', function () {
    $response = $this->get('/login');

    $response->assertNotFound();
});

test('filament user login screen can be rendered', function () {
    $response = $this->get('/app/login');

    $response->assertStatus(200);
});

test('guests are redirected to filament login when opening user panel', function () {
    $response = $this->get('/app');

    $response->assertRedirect('/app/login');
});

test('authenticated users can access user panel', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/app');

    $response->assertStatus(302);
    expect($response->headers->get('Location'))->not->toContain('/app/login');
});

test('admin users can access admin panel', function () {
    $user = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertStatus(302);
    expect($response->headers->get('Location'))->not->toContain('/admin/login');
});

test('non-admin users cannot access admin panel', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertForbidden();
});

test('users can logout from filament user panel', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/app/logout');

    $response->assertStatus(302);
});
