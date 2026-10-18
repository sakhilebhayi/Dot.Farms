<?php

namespace App\Policies\Concerns;

use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Team;
use App\Models\User;

/**
 * The write rules for everything in the agriculture domain, in one place.
 *
 * Roles are Jetstream's team roles. The team owner always counts as `admin`.
 *
 *   manage  (owner, admin)  create / edit / delete farms, fields and crops;
 *                           delete cycles, plantings and harvests
 *   operate (+ editor)      plan and edit crop cycles, move them through their
 *                           lifecycle, record and correct plantings and harvests
 *   read    (any member)    view everything and export harvests
 *
 * An editor can therefore fix a mistaken harvest by editing it, but only an
 * owner/admin can remove records or change the farm structure. A member whose
 * role is neither admin nor editor is read-only.
 *
 * Team membership is resolved through the parent chain with the model scopes
 * switched off, so these checks are independent of the global team scope:
 * the scope hides foreign rows, the policy still says no if one ever got here.
 */
trait ChecksFarmRoles
{
    protected function isMember(User $user, ?Team $team): bool
    {
        return $team !== null && $user->belongsToTeam($team);
    }

    protected function canManage(User $user, ?Team $team): bool
    {
        return $this->isMember($user, $team) && $user->hasTeamRole($team, 'admin');
    }

    protected function canOperate(User $user, ?Team $team): bool
    {
        return $this->isMember($user, $team)
            && ($user->hasTeamRole($team, 'admin') || $user->hasTeamRole($team, 'editor'));
    }

    protected function teamOfFarmId(?int $farmId): ?Team
    {
        $teamId = Farm::withoutGlobalScopes()->whereKey($farmId)->value('team_id');

        return $teamId ? Team::find($teamId) : null;
    }

    protected function teamOfField(Field $field): ?Team
    {
        return $this->teamOfFarmId($field->farm_id);
    }

    protected function teamOfCycle(CropCycle $cycle): ?Team
    {
        $farmId = Field::withoutGlobalScopes()->whereKey($cycle->field_id)->value('farm_id');

        return $this->teamOfFarmId($farmId);
    }

    protected function currentTeam(User $user): ?Team
    {
        return $user->currentTeam;
    }
}
