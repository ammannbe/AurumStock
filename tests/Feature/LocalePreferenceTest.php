<?php

use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('applies the authenticated users preferred locale', function () {
    $user = User::factory()->create([
        'preferred_locale' => 'de',
    ]);

    Route::middleware('web')->get('/__locale-check', fn () => [
        'locale' => App::getLocale(),
    ]);

    App::setLocale('en');

    actingAs($user);

    get('/__locale-check')
        ->assertOk()
        ->assertJsonPath('locale', 'de');
});
