<?php

namespace App\Notifications;

use App\Models\HarvestRecord;
use Illuminate\Notifications\Notification;

/**
 * In-app (database channel only) notification for a newly recorded
 * harvest. Not yet wired to any automatic trigger — dispatch manually
 * via `$user->notify(new HarvestRecordedNotification($harvestRecord))`
 * until the `agriculture.harvest.recorded` event (wiki.md §5) is
 * actually published anywhere.
 */
class HarvestRecordedNotification extends Notification
{
    public function __construct(public HarvestRecord $harvestRecord) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $this->harvestRecord->loadMissing('cropCycle.crop', 'cropCycle.field.farm');
        $crop = $this->harvestRecord->cropCycle->crop;
        $farm = $this->harvestRecord->cropCycle->field->farm;

        return [
            'type' => 'harvest_recorded',
            'title' => 'Harvest recorded',
            'message' => number_format((float) $this->harvestRecord->quantity_harvested, 2)." {$this->harvestRecord->unit} of {$crop->name} harvested at {$farm->name}.",
            'harvest_record_id' => $this->harvestRecord->id,
            'url' => route('harvests.show', $this->harvestRecord),
        ];
    }
}
