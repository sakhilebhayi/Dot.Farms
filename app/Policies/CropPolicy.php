<?php

namespace App\Policies;

use App\Models\Crop;
use App\Models\Team;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRoles;

/** The crop catalog is team structure: owner/admin write, any member reads. See ChecksFarmRoles. */
class CropPolicy
{
    use ChecksFarmRoles;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Crop $crop): bool
    {
        return $this->isMember($user, Team::find($crop->team_id));
    }

    public function create(User $user): bool
    {
        return $this->canManage($user, $this->currentTeam($user));
    }

    public function update(User $user, Crop $crop): bool
    {
        return $this->canManage($user, Team::find($crop->team_id));
    }

    public function delete(User $user, Crop $crop): bool
    {
        return $this->canManage($user, Team::find($crop->team_id));
    }

    public function restore(User $user, Crop $crop): bool
    {
        return $this->canManage($user, Team::find($crop->team_id));
    }
}
