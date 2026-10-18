<?php

namespace Tests\Feature\Farms;

use App\Models\Crop;
use App\Models\PlantingRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsFarmWorld;
use Tests\TestCase;

/**
 * The write policy, as a table. Roles are Jetstream's: the team owner and
 * `admin` manage everything; `editor` runs the season (plans and edits
 * cycles, records and corrects plantings and harvests, moves cycles through
 * their lifecycle) but cannot create, edit or delete farms, fields or crops
 * and cannot delete anything; any other member can only read.
 */
class FarmAuthorizationTest extends TestCase
{
    use BuildsFarmWorld;
    use RefreshDatabase;

    /** action => [owner, admin, editor, read-only member] */
    public static function matrix(): array
    {
        $manageOnly = [true, true, false, false];
        $operate = [true, true, true, false];

        $rows = [
            'create farm' => $manageOnly,
            'update farm' => $manageOnly,
            'delete farm' => $manageOnly,
            'create field' => $manageOnly,
            'update field' => $manageOnly,
            'delete field' => $manageOnly,
            'create crop' => $manageOnly,
            'update crop' => $manageOnly,
            'delete crop' => $manageOnly,
            'plan cycle' => $operate,
            'update cycle' => $operate,
            'transition cycle' => $operate,
            'delete cycle' => $manageOnly,
            'record planting' => $operate,
            'update planting' => $operate,
            'delete planting' => $manageOnly,
            'record harvest' => $operate,
            'update harvest' => $operate,
            'delete harvest' => $manageOnly,
        ];

        $cases = [];
        foreach ($rows as $action => $allowed) {
            foreach (['owner', 'admin', 'editor', 'viewer'] as $i => $role) {
                $cases["$role can".($allowed[$i] ? '' : 'not')." $action"] = [$role, $action, $allowed[$i]];
            }
        }

        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_role_matrix(string $role, string $action, bool $allowed): void
    {
        $team = $this->teamWithOwner();
        $w = $this->world($team, in_array($action, ['record planting', 'update planting', 'delete planting', 'plan cycle'], true) ? 'planted' : 'growing');
        if ($action === 'delete crop') {
            $unused = Crop::factory()->create(['team_id' => $team->id]);
        }
        if ($action === 'delete planting') {
            // The only planting of a cycle that has harvests cannot go; give it a sibling.
            PlantingRecord::factory()->create(['crop_cycle_id' => $w->cycle->id, 'planted_at' => now()->subMonths(3)]);
        }
        $user = $this->memberOf($team, $role);
        $this->actingAs($user);

        $response = match ($action) {
            'create farm' => $this->post(route('farms.store'), ['name' => 'New']),
            'update farm' => $this->put(route('farms.update', $w->farm), ['name' => 'Renamed', 'status' => 'active']),
            'delete farm' => $this->delete(route('farms.destroy', $w->farm), ['confirm_cascade' => 1]),
            'create field' => $this->post(route('fields.store', $w->farm), ['name' => 'F', 'code' => 'NEWF']),
            'update field' => $this->put(route('fields.update', [$w->farm, $w->field]), ['name' => 'Renamed', 'code' => $w->field->code, 'status' => 'active']),
            'delete field' => $this->delete(route('fields.destroy', [$w->farm, $w->field]), ['confirm_cascade' => 1]),
            'create crop' => $this->post(route('crops.store'), ['name' => 'Rye']),
            'update crop' => $this->put(route('crops.update', $w->crop), ['name' => 'Renamed']),
            'delete crop' => $this->delete(route('crops.destroy', $unused)),
            'plan cycle' => $this->post(route('crop-cycles.store', [$w->farm, $w->field]), ['crop_id' => $w->crop->id, 'season' => '2027 Summer']),
            'update cycle' => $this->put(route('crop-cycles.update', $this->cycleRouteParams($w)), ['season' => '2026 Winter']),
            'transition cycle' => $this->post(route('crop-cycles.transition', $this->cycleRouteParams($w)), ['action' => 'abandon', 'reason' => 'Test']),
            'delete cycle' => $this->delete(route('crop-cycles.destroy', $this->cycleRouteParams($w)), ['confirm_cascade' => 1]),
            'record planting' => $this->post(route('crop-cycles.planting.store', $this->cycleRouteParams($w)), ['planted_at' => now()->subMonths(4)->toDateString(), 'unit' => 'kg']),
            'update planting' => $this->put(route('plantings.update', [...$this->cycleRouteParams($w), $w->planting]), ['planted_at' => now()->subMonths(4)->toDateString(), 'unit' => 'kg']),
            'delete planting' => $this->delete(route('plantings.destroy', [...$this->cycleRouteParams($w), $w->planting])),
            'record harvest' => $this->post(route('crop-cycles.harvest.store', $this->cycleRouteParams($w)), ['harvested_at' => now()->toDateString(), 'quantity_harvested' => 5, 'unit' => 'kg']),
            'update harvest' => $this->put(route('harvests.update', $w->harvest), ['harvested_at' => now()->subDay()->toDateString(), 'quantity_harvested' => 5, 'unit' => 'kg']),
            'delete harvest' => $this->delete(route('harvests.destroy', $w->harvest)),
        };

        if ($allowed) {
            $this->assertNotSame(403, $response->getStatusCode(), "$role was refused $action");
            $response->assertSessionHasNoErrors();
        } else {
            $response->assertForbidden();
        }
    }

    public function test_every_member_can_read_everything(): void
    {
        $team = $this->teamWithOwner();
        $w = $this->world($team);

        foreach (['owner', 'admin', 'editor', 'viewer'] as $role) {
            $this->actingAs($this->memberOf($team, $role));

            $this->get(route('farms.show', $w->farm))->assertOk();
            $this->get(route('fields.show', [$w->farm, $w->field]))->assertOk();
            $this->get(route('crop-cycles.show', $this->cycleRouteParams($w)))->assertOk();
            $this->get(route('harvests.show', $w->harvest))->assertOk();
            $this->get(route('harvests.index'))->assertOk();
            $this->get(route('crops.index'))->assertOk();
        }
    }

    public function test_edit_screens_follow_the_same_rules(): void
    {
        $team = $this->teamWithOwner();
        $w = $this->world($team);

        $editor = $this->memberOf($team, 'editor');
        $this->actingAs($editor);

        $this->get(route('farms.edit', $w->farm))->assertForbidden();
        $this->get(route('fields.edit', [$w->farm, $w->field]))->assertForbidden();
        $this->get(route('crops.edit', $w->crop))->assertForbidden();
        $this->get(route('farms.create'))->assertForbidden();
        $this->get(route('crops.create'))->assertForbidden();
        $this->get(route('fields.create', $w->farm))->assertForbidden();

        $this->get(route('crop-cycles.edit', $this->cycleRouteParams($w)))->assertOk();
        $this->get(route('plantings.edit', [...$this->cycleRouteParams($w), $w->planting]))->assertOk();
        $this->get(route('harvests.edit', $w->harvest))->assertOk();
        $this->get(route('crop-cycles.create', [$w->farm, $w->field]))->assertOk();
    }

    public function test_pages_hide_controls_the_user_may_not_use(): void
    {
        $team = $this->teamWithOwner();
        $w = $this->world($team);

        $this->actingAs($this->memberOf($team, 'editor'));
        $this->get(route('farms.show', $w->farm))
            ->assertOk()
            ->assertDontSee(route('farms.edit', $w->farm), false)
            ->assertDontSee(route('fields.create', $w->farm), false);
        $this->get(route('crops.index'))->assertDontSee(route('crops.create'), false);
        $this->get(route('crop-cycles.show', $this->cycleRouteParams($w)))
            ->assertOk()
            ->assertSee(route('harvests.edit', $w->harvest), false)
            ->assertDontSee('action="'.route('harvests.destroy', $w->harvest).'"', false);

        $this->actingAs($this->memberOf($team, 'viewer'));
        $this->get(route('crop-cycles.show', $this->cycleRouteParams($w)))
            ->assertOk()
            ->assertDontSee(route('harvests.edit', $w->harvest), false)
            ->assertDontSee(route('crop-cycles.harvest.store', $this->cycleRouteParams($w)), false);

        $this->actingAs($team->owner);
        $this->get(route('crop-cycles.show', $this->cycleRouteParams($w)))
            ->assertSee(route('harvests.edit', $w->harvest), false)
            ->assertSee(route('crop-cycles.planting.store', $this->cycleRouteParams($w)), false);
    }
}
