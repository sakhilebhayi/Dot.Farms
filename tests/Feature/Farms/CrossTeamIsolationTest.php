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
 *
 * Farm and Crop now also carry HasTeamScope (app/Models/Concerns/HasTeamScope.php),
 * a global scope that limits every query -- including implicit route-model
 * binding -- to the authenticated user's current team. That means a Farm
 * belonging to another team is no longer just policy-denied, it's invisible
 * to the query that resolves the {farm} route parameter in the first place:
 * routes bound directly on Farm (or on Field via its {farm} sibling
 * parameter) now 404 instead of 403 for cross-team access, because the row
 * never reaches the point where FarmPolicy would run. This is a stronger,
 * fail-closed posture than before -- it no longer depends on every route
 * remembering Gate::authorize() -- so the assertions below were updated
 * from assertForbidden() to assertNotFound() to match. See
 * test_scope_alone_blocks_cross_team_access_even_without_a_policy_check
 * below for a regression test proving the scope alone (no Policy in the
 * path) is what's doing the blocking.
 */
class CrossTeamIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_view_another_teams_farm(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherTeam = Team::factory()->create();
        $otherFarm = Farm::factory()->create(['team_id' => $otherTeam->id]);

        // Farm's HasTeamScope makes $otherFarm invisible to the route-model
        // binding query before FarmPolicy ever runs -- 404, not 403.
        $this->actingAs($user)
            ->get(route('farms.show', $otherFarm))
            ->assertNotFound();
    }

    public function test_user_cannot_view_another_teams_field(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherTeam = Team::factory()->create();
        $otherFarm = Farm::factory()->create(['team_id' => $otherTeam->id]);
        $otherField = Field::factory()->create(['farm_id' => $otherFarm->id]);

        // Same reasoning: the {farm} route parameter can't resolve
        // $otherFarm at all once it's scoped out of the current team.
        $this->actingAs($user)
            ->get(route('fields.show', [$otherFarm, $otherField]))
            ->assertNotFound();
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

        // Same reasoning: {farm} can't resolve, so this 404s before
        // FieldController::store's Gate::authorize('update', $farm) runs.
        $this->actingAs($user)
            ->post(route('fields.store', $otherFarm), [
                'name' => 'Intruder Field',
                'code' => 'INT-1',
            ])
            ->assertNotFound();
    }

    /**
     * Modeled directly on Dot.Finance's
     * test_scope_alone_blocks_cross_user_access_even_without_a_policy_check
     * (tests/Feature/FinanceAuthorizationTest.php). Proves the HasTeamScope
     * global scope itself -- not FarmPolicy -- is what blocks cross-team
     * reads: querying Farm directly (no controller, no Gate::authorize call
     * anywhere in the path) while acting as a user on a different team
     * must not return the other team's row.
     */
    public function test_scope_alone_blocks_cross_team_access_even_without_a_policy_check(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherTeam = Team::factory()->create();
        $otherFarm = Farm::factory()->create(['team_id' => $otherTeam->id]);

        $this->actingAs($user);

        $this->assertNull(Farm::find($otherFarm->id));
        $this->assertFalse(Farm::query()->pluck('id')->contains($otherFarm->id));
    }
}
