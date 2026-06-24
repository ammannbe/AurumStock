<?php

use App\Enums\Currency;
use App\Filament\Pages\User\Profile;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

/** @var TestCase $this */
test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/app/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(Profile::class); // @phpstan-ignore argument.templateType

    $component
        ->set('data.name', 'Test User')
        ->set('data.email', 'test@example.com')
        ->set('data.preferred_currency', Currency::EUR->value)
        ->set('data.notes', 'Updated notes from profile page')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertSame(Currency::EUR, $user->preferred_currency);
    $this->assertSame('Updated notes from profile page', $user->notes);
});
