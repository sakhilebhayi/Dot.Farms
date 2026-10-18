<?php

namespace App\Notifications;

use App\Models\HarvestRecord;
use App\Notifications\Concerns\SendsNotifyEvent;
use App\Services\Notify\NotifyClient;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * In-app (database channel) notification for a newly recorded harvest.
 * Fired automatically for every member of the harvest's owning team by
 * App\Observers\HarvestRecordObserver, registered against HarvestRecord in
 * AppServiceProvider::boot(). The `agriculture.harvest.recorded` Knowledge
 * Pack event (wiki.md §5) is still not published anywhere — this
 * notification is purely in-app, not the cross-platform ecosystem
 * integration.
 *
 * Also broadcasts over Reverb so NotificationBell.php updates live instead
 * of only on page load -- see routes/channels.php for the
 * App.Models.User.{id} channel this uses.
 */
class HarvestRecordedNotification extends Notification
{
    use SendsNotifyEvent;

    public function __construct(public HarvestRecord $harvestRecord) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', ...$this->notifyChannel()];
    }

    /**
     * The email-worthy copy of this notice for Dot.Notify (harvest.recorded), for the same team
     * members who get the in-app notice. The company is the harvest's own team (the owner of the
     * data), never the recipient's current team, so a harvest is never announced on behalf of
     * another company. A team not linked to InfoDot has no uuid and is skipped.
     *
     * @return array{company_uuid: string|null, event: string, subject: int, bucket: string, data: array<string, string>}
     */
    public function toNotifyEvent(object $notifiable): array
    {
        $this->harvestRecord->loadMissing('cropCycle.crop', 'cropCycle.field.farm.team');
        $cycle = $this->harvestRecord->cropCycle;
        $field = $cycle->field;
        $farm = $field->farm;

        return [
            'company_uuid' => $farm->team?->infodot_team_uuid,
            'event' => 'harvest.recorded',
            'subject' => $this->harvestRecord->id,
            'bucket' => 'recorded',
            'data' => [
                'farm_name' => (string) $farm->name,
                'field_name' => (string) $field->name,
                'crop' => (string) $cycle->crop->name,
                'quantity' => number_format((float) $this->harvestRecord->quantity_harvested, 2, '.', ''),
                'unit' => (string) $this->harvestRecord->unit,
                'harvested_at' => $this->harvestRecord->harvested_at?->toIso8601String() ?? '',
                'url' => app(NotifyClient::class)->pageUrl('harvests.show', $this->harvestRecord),
            ],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
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
