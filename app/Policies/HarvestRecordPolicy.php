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

    /**
     * The harvest CSV is the data the list and the reports already show, so it is
     * open to whoever may read them: every member, viewers included, because editors
     * may. Tighten it here, in one place, if that decision changes.
     */
    public function export(User $user): bool
    {
        return $this->currentTeam($user) !== null && $this->isMember($user, $this->currentTeam($user));
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
