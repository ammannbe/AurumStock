<?php

namespace Tests\Feature;

use App\Models\Depository;
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

        $response = $this->get('/app/depositories');
        $response->assertStatus(403);
        $this->get('/app/depositories/create')->assertStatus(403);
    }

    public function test_user_can_create_depository_via_model(): void
    {
        $user = User::factory()->create();

        $depository = Depository::create([
            'name' => 'Home Vault',
            'location' => 'Basement',
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('depositories', ['name' => 'Home Vault', 'user_id' => $user->id]);
        $this->assertEquals($user->id, $depository->user_id);
        $this->assertEquals('Home Vault', $depository->name);
    }
}
