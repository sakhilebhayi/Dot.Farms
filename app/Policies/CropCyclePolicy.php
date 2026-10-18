<?php

namespace App\Policies;

use App\Models\CropCycle;
use App\Models\Field;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRoles;

/**
 * Running a season is operational work: owner, admin and editor may plan and
 * edit cycles, move them through their lifecycle and record plantings and
 * harvests against them. Deleting a cycle is owner/admin only.
 * See ChecksFarmRoles.
 */
class CropCyclePolicy
{
    use ChecksFarmRoles;

    public function view(User $user, CropCycle $cycle): bool
    {
        return $this->isMember($user, $this->teamOfCycle($cycle));
    }

    public function create(User $user, Field $field): bool
    {
        return $this->canOperate($user, $this->teamOfField($field));
    }

    public function update(User $user, CropCycle $cycle): bool
    {
        return $this->canOperate($user, $this->teamOfCycle($cycle));
    }

    /** Mark growing, close, fail, abandon, re-open. */
    public function transition(User $user, CropCycle $cycle): bool
    {
        return $this->update($user, $cycle);
    }

    public function recordPlanting(User $user, CropCycle $cycle): bool
    {
        return $this->update($user, $cycle);
    }

    public function recordHarvest(User $user, CropCycle $cycle): bool
    {
        return $this->update($user, $cycle);
    }

    public function delete(User $user, CropCycle $cycle): bool
    {
        return $this->canManage($user, $this->teamOfCycle($cycle));
    }
}
