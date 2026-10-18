<?php

namespace Tests\Feature\Farms;

use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\PlantingRecord;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TeamScopeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{team: Team, farm: Farm, field: Field, crop: Crop, cycle: CropCycle, planting: PlantingRecord, harvest: HarvestRecord} */
    private function world(Team $team): array
    {
        $farm = Farm::factory()->create(['team_id' => $team->id]);
        $field = Field::factory()->create(['farm_id' => $farm->id]);
        $crop = Crop::factory()->create(['team_id' => $team->id]);
        $cycle = CropCycle::factory()->create(['field_id' => $field->id, 'crop_id' => $crop->id]);

        return [
            'team' => $team,
            'farm' => $farm,
            'field' => $field,
            'crop' => $crop,
            'cycle' => $cycle,
            'planting' => PlantingRecord::factory()->create(['crop_cycle_id' => $cycle->id]),
            'harvest' => HarvestRecord::factory()->create(['crop_cycle_id' => $cycle->id]),
        ];
    }

    public function test_every_model_in_the_chain_is_invisible_to_another_teams_user(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $mine = $this->world($user->currentTeam);
        $theirs = $this->world(Team::factory()->create());

        $this->actingAs($user);

        $this->assertSame([$mine['farm']->id], Farm::pluck('id')->all());
        $this->assertSame([$mine['field']->id], Field::pluck('id')->all());
        $this->assertSame([$mine['crop']->id], Crop::pluck('id')->all());
        $this->assertSame([$mine['cycle']->id], CropCycle::pluck('id')->all());
        $this->assertSame([$mine['planting']->id], PlantingRecord::pluck('id')->all());
        $this->assertSame([$mine['harvest']->id], HarvestRecord::pluck('id')->all());

        $this->assertNull(Field::find($theirs['field']->id));
        $this->assertNull(CropCycle::find($theirs['cycle']->id));
        $this->assertNull(PlantingRecord::find($theirs['planting']->id));
        $this->assertNull(HarvestRecord::find($theirs['harvest']->id));
    }

    public function test_the_scope_fails_closed_for_an_authenticated_user_with_no_current_team(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);
        $this->world(Team::factory()->create());

        $this->actingAs($user);

        $this->assertSame(0, Farm::count());
        $this->assertSame(0, Crop::count());
        $this->assertSame(0, Field::count());
        $this->assertSame(0, CropCycle::count());
        $this->assertSame(0, PlantingRecord::count());
        $this->assertSame(0, HarvestRecord::count());
    }

    public function test_the_console_and_queue_context_is_not_scoped(): void
    {
        $this->world(Team::factory()->create());
        $this->world(Team::factory()->create());

        Auth::logout();

        $this->assertFalse(Auth::check());
        $this->assertSame(2, Farm::count());
        $this->assertSame(2, Field::count());
        $this->assertSame(2, HarvestRecord::count());
    }

    public function test_for_team_scopes_without_any_authenticated_user(): void
    {
        $a = $this->world(Team::factory()->create());
        $b = $this->world(Team::factory()->create());

        $this->assertSame([$a['field']->id], Field::forTeam($a['team'])->pluck('id')->all());
        $this->assertSame([$b['cycle']->id], CropCycle::forTeam($b['team'])->pluck('id')->all());
        $this->assertSame([$a['planting']->id], PlantingRecord::forTeam($a['team']->id)->pluck('id')->all());
        $this->assertSame([$b['harvest']->id], HarvestRecord::forTeam($b['team'])->pluck('id')->all());
    }

    public function test_for_team_with_no_team_returns_nothing(): void
    {
        $this->world(Team::factory()->create());

        $this->assertSame(0, Field::forTeam(null)->count());
        $this->assertSame(0, HarvestRecord::forTeam(null)->count());
    }

    public function test_records_under_a_deleted_parent_drop_out_of_for_team(): void
    {
        $a = $this->world(Team::factory()->create());

        // Soft-delete the farm without the model cascade to prove the scope itself
        // refuses to follow a deleted parent.
        Farm::withoutEvents(fn () => $a['farm']->delete());

        $this->assertSame(0, Field::forTeam($a['team'])->count());
        $this->assertSame(0, CropCycle::forTeam($a['team'])->count());
        $this->assertSame(0, HarvestRecord::forTeam($a['team'])->count());
    }

    public function test_a_user_cannot_resolve_a_foreign_record_even_by_primary_key(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $theirs = $this->world(Team::factory()->create());

        $this->actingAs($user);

        $this->assertNull(Field::query()->whereKey($theirs['field']->id)->first());
        $this->assertSame(0, HarvestRecord::query()->whereKey($theirs['harvest']->id)->count());
    }
}
