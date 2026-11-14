<?php

use App\Filament\Pages\User\ChangePassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/** @var TestCase $this */
test('change password page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/app/change-password');

    $response->assertOk();
});

test('password can be changed from change password page', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ChangePassword::class);

    $component
        ->set('data.current_password', 'password')
        ->set('data.password', 'new-secure-password')
        ->set('data.password_confirmation', 'new-secure-password')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertTrue(Hash::check('new-secure-password', $user->refresh()->password));
});

test('password is not changed with invalid current password', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ChangePassword::class);

    $component
        ->set('data.current_password', 'wrong-password')
        ->set('data.password', 'new-secure-password')
        ->set('data.password_confirmation', 'new-secure-password')
        ->call('save')
        ->assertHasErrors();

    $this->assertTrue(Hash::check('password', $user->refresh()->password));
});
