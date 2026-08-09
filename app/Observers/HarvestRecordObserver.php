<?php

namespace App\Observers;

use App\Models\HarvestRecord;
use App\Notifications\HarvestRecordedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Fires HarvestRecordedNotification for every member of the harvest's
 * owning team, on every HarvestRecord creation regardless of entry point
 * (controller, factory, seeder, or a future API) — see
 * docs/superpowers/specs/2026-08-09-harvest-notification-wiring-design.md.
 */
class HarvestRecordObserver
{
    public function created(HarvestRecord $harvestRecord): void
    {
        $harvestRecord->loadMissing('cropCycle.field.farm.team');

        $team = $harvestRecord->cropCycle->field->farm->team;

        Notification::send($team->allUsers(), new HarvestRecordedNotification($harvestRecord));
    }
}
