<?php

namespace App\Observers;

use App\Models\HarvestRecord;
use App\Notifications\HarvestRecordedNotification;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Notification;

/**
 * Fires HarvestRecordedNotification for every member of the harvest's
 * owning team, on every HarvestRecord creation regardless of entry point
 * (controller, factory, seeder, or a future API) — see
 * docs/superpowers/specs/2026-08-09-harvest-notification-wiring-design.md.
 *
 * ShouldHandleEventsAfterCommit: the harvest is now recorded inside a
 * transaction together with the crop-cycle status change (CropCycleLifecycle).
 * Notifying (and broadcasting to the live bell) only after that transaction
 * commits means a rolled-back harvest never tells anyone it happened.
 */
class HarvestRecordObserver implements ShouldHandleEventsAfterCommit
{
    public function created(HarvestRecord $harvestRecord): void
    {
        $harvestRecord->loadMissing('cropCycle.field.farm.team');

        $team = $harvestRecord->cropCycle->field->farm->team;

        Notification::send($team->allUsers(), new HarvestRecordedNotification($harvestRecord));
    }
}
