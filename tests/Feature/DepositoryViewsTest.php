<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositoryViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_manage_depositories()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/depositories')->assertStatus(200);
        $this->get('/depositories/create')->assertStatus(200);

        $this->post('/depositories', [
            'name' => 'Home Vault',
            'location' => 'Basement',
        ])->assertRedirect('/depositories');

        $this->assertDatabaseHas('depositories', ['name' => 'Home Vault', 'user_id' => $user->id]);
    }
}
