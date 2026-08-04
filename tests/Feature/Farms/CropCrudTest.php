<?php

namespace Tests\Feature\Farms;

use App\Models\Crop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CropCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_member_can_create_a_crop(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->post('/crops', [
            'name' => 'Maize',
            'variety' => 'Yellow Dent',
        ]);

        $response->assertRedirect(route('crops.index'));
        $this->assertDatabaseHas('crops', [
            'team_id' => $user->currentTeam->id,
            'name' => 'Maize',
        ]);
    }

    public function test_user_with_no_team_cannot_create_a_crop(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);

        $this->actingAs($user)
            ->post('/crops', ['name' => 'Maize'])
            ->assertForbidden();

        $this->assertDatabaseCount('crops', 0);
    }
}
