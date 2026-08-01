<?php

namespace Tests\Feature\Farms;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_member_can_create_a_farm(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->post('/farms', [
            'name' => 'Vaalharts Farm',
            'location' => 'Vaalharts, South Africa',
            'size_hectares' => 120.5,
        ]);

        $farm = Farm::first();

        $response->assertRedirect(route('farms.show', $farm));
        $this->assertDatabaseHas('farms', [
            'team_id' => $user->currentTeam->id,
            'name' => 'Vaalharts Farm',
            'status' => 'active',
        ]);
    }

    public function test_farm_requires_a_name(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->post('/farms', ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_farms_index_lists_only_the_current_teams_farms(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $ownFarm = Farm::factory()->create(['team_id' => $user->currentTeam->id, 'name' => 'Own Farm']);

        $otherTeam = \App\Models\Team::factory()->create();
        Farm::factory()->create(['team_id' => $otherTeam->id, 'name' => 'Other Team Farm']);

        $this->actingAs($user)
            ->get('/farms')
            ->assertOk()
            ->assertSee('Own Farm')
            ->assertDontSee('Other Team Farm');
    }

    public function test_team_member_can_view_own_farm(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $farm = Farm::factory()->create(['team_id' => $user->currentTeam->id, 'name' => 'My Farm']);

        $this->actingAs($user)
            ->get(route('farms.show', $farm))
            ->assertOk()
            ->assertSee('My Farm');
    }
}
