<?php

namespace App\Policies;

use App\Models\CropCycle;
use App\Models\PlantingRecord;
use App\Models\Team;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRoles;

/** Editors may record and correct plantings; only owner/admin may delete one. See ChecksFarmRoles. */
class PlantingRecordPolicy
{
    use ChecksFarmRoles;

    public function view(User $user, PlantingRecord $record): bool
    {
        return $this->isMember($user, $this->teamOfRecord($record));
    }

    public function update(User $user, PlantingRecord $record): bool
    {
        return $this->canOperate($user, $this->teamOfRecord($record));
    }

    public function delete(User $user, PlantingRecord $record): bool
    {
        return $this->canManage($user, $this->teamOfRecord($record));
    }

    private function teamOfRecord(PlantingRecord $record): ?Team
    {
        $cycle = CropCycle::withoutGlobalScopes()->find($record->crop_cycle_id);

        return $cycle ? $this->teamOfCycle($cycle) : null;
    }
}
