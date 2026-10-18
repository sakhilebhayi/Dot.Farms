<?php

namespace App\Policies;

use App\Models\CropCycle;
use App\Models\HarvestRecord;
use App\Models\Team;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRoles;

/**
 * Editors may record and correct harvests (so a mistaken harvest can always
 * be fixed); only owner/admin may delete one. Any member may list and export.
 * See ChecksFarmRoles.
 */
class HarvestRecordPolicy
{
    use ChecksFarmRoles;

    public function viewAny(User $user): bool
    {
        return $this->currentTeam($user) !== null;
    }

    public function view(User $user, HarvestRecord $record): bool
    {
        return $this->isMember($user, $this->teamOfRecord($record));
    }

    public function update(User $user, HarvestRecord $record): bool
    {
        return $this->canOperate($user, $this->teamOfRecord($record));
    }

    public function delete(User $user, HarvestRecord $record): bool
    {
        return $this->canManage($user, $this->teamOfRecord($record));
    }

    private function teamOfRecord(HarvestRecord $record): ?Team
    {
        $cycle = CropCycle::withoutGlobalScopes()->find($record->crop_cycle_id);

        return $cycle ? $this->teamOfCycle($cycle) : null;
    }
}
