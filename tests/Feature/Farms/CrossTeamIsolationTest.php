<?php

namespace Tests\Feature\Farms;

use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mirrors Dot.Billing's InvoiceViewTest::test_user_cannot_view_another_teams_invoice,
 * which was added there after a security-review pass found no authorization
 * check on invoice access. FarmPolicy applies the same team-ownership check
 * here from the start (see app/Policies/FarmPolicy.php).
 */
class CrossTeamIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_view_another_teams_farm(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherTeam = Team::factory()->create();
        $otherFarm = Farm::factory()->create(['team_id' => $otherTeam->id]);

        $this->actingAs($user)
            ->get(route('farms.show', $otherFarm))
            ->assertForbidden();
    }

    public function test_user_cannot_view_another_teams_field(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherTeam = Team::factory()->create();
        $otherFarm = Farm::factory()->create(['team_id' => $otherTeam->id]);
        $otherField = Field::factory()->create(['farm_id' => $otherFarm->id]);

        $this->actingAs($user)
            ->get(route('fields.show', [$otherFarm, $otherField]))
            ->assertForbidden();
    }

    public function test_user_cannot_view_another_teams_harvest_record(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $otherTeam = Team::factory()->create();
        $otherFarm = Farm::factory()->create(['team_id' => $otherTeam->id]);
        $otherField = Field::factory()->create(['farm_id' => $otherFarm->id]);
        $otherCrop = Crop::factory()->create(['team_id' => $otherTeam->id]);
        $otherCycle = CropCycle::factory()->create([
            'field_id' => $otherField->id,
            'crop_id' => $otherCrop->id,
        ]);
        $otherHarvest = HarvestRecord::factory()->create(['crop_cycle_id' => $otherCycle->id]);

        $this->actingAs($user)
            ->get(route('harvests.show', $otherHarvest))
            ->assertForbidden();
    }

    public function test_user_cannot_add_a_field_to_another_teams_farm(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherTeam = Team::factory()->create();
        $otherFarm = Farm::factory()->create(['team_id' => $otherTeam->id]);

        $this->actingAs($user)
            ->post(route('fields.store', $otherFarm), [
                'name' => 'Intruder Field',
                'code' => 'INT-1',
            ])
            ->assertForbidden();
    }
}
