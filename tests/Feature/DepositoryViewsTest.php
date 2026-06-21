<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositoryViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_manage_depositories(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/depositories');
        $response->assertRedirect('/app/depositories');
        $this->get('/depositories/create')->assertRedirect('/app/depositories');

        $this->post('/depositories', [
            'name' => 'Home Vault',
            'location' => 'Basement',
        ])->assertRedirect('/depositories');

        $this->assertDatabaseHas('depositories', ['name' => 'Home Vault', 'user_id' => $user->id]);
    }
}
