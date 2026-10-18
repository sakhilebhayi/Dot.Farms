<?php

namespace Tests\Concerns;

use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\PlantingRecord;
use App\Models\Team;
use App\Models\User;

/**
 * Builds a team's farm -> field -> cycle -> records chain, and users with a
 * given Jetstream role on a team.
 */
trait BuildsFarmWorld
{
    /**
     * @return object{team: Team, farm: Farm, field: Field, crop: Crop, cycle: CropCycle, planting: PlantingRecord, harvest: HarvestRecord}
     */
    protected function world(?Team $team = null, string $cycleState = 'growing'): object
    {
        $team ??= Team::factory()->create();
        $farm = Farm::factory()->create(['team_id' => $team->id]);
        $field = Field::factory()->create(['farm_id' => $farm->id]);
        $crop = Crop::factory()->create(['team_id' => $team->id]);
        $cycle = CropCycle::factory()->{$cycleState}()->create([
            'field_id' => $field->id,
            'crop_id' => $crop->id,
            'planted_at' => now()->subMonths(3),
        ]);
        $planting = PlantingRecord::factory()->create(['crop_cycle_id' => $cycle->id, 'planted_at' => now()->subMonths(3), 'unit' => 'kg']);
        $harvest = HarvestRecord::factory()->create(['crop_cycle_id' => $cycle->id, 'harvested_at' => now()->subWeek(), 'unit' => 'kg', 'quantity_harvested' => 100]);

        return (object) compact('team', 'farm', 'field', 'crop', 'cycle', 'planting', 'harvest');
    }

    /** A user who is a member of $team with $role (owner, admin, editor, or any other string for a read-only member). */
    protected function memberOf(Team $team, string $role): User
    {
        if ($role === 'owner') {
            return $team->owner;
        }

        $user = User::factory()->create();
        $team->users()->attach($user, ['role' => $role]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    /** A team with a real owner, ready for memberOf(). */
    protected function teamWithOwner(): Team
    {
        $owner = User::factory()->withPersonalTeam()->create();

        return $owner->currentTeam;
    }

    /** Routes for the nested resources of a world. */
    protected function cycleRouteParams(object $w): array
    {
        return [$w->farm, $w->field, $w->cycle];
    }
}
