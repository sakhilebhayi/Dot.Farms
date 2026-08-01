<?php

namespace Tests\Feature\Farms;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_member_can_add_a_field_to_their_farm(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $farm = Farm::factory()->create(['team_id' => $user->currentTeam->id]);

        $response = $this->actingAs($user)->post(route('fields.store', $farm), [
            'name' => 'North Paddock',
            'code' => 'N-1',
            'soil_type' => 'loam',
            'moisture_zone' => 'moderate',
            'size_hectares' => 12.5,
        ]);

        $this->assertDatabaseHas('fields', [
            'farm_id' => $farm->id,
            'name' => 'North Paddock',
            'code' => 'N-1',
            'status' => 'active',
        ]);

        $field = $farm->fields()->first();
        $response->assertRedirect(route('fields.show', [$farm, $field]));
    }

    public function test_field_requires_a_unique_code_per_farm(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $farm = Farm::factory()->create(['team_id' => $user->currentTeam->id]);
        $farm->fields()->create([
            'name' => 'North Paddock',
            'code' => 'N-1',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('fields.store', $farm), [
                'name' => 'Duplicate Paddock',
                'code' => 'N-1',
            ])
            ->assertSessionHasErrors();
    }

    public function test_team_member_can_view_a_field(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $farm = Farm::factory()->create(['team_id' => $user->currentTeam->id]);
        $field = $farm->fields()->create([
            'name' => 'South Paddock',
            'code' => 'S-1',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('fields.show', [$farm, $field]))
            ->assertOk()
            ->assertSee('South Paddock');
    }
}
