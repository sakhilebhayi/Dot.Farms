<?php

namespace Tests\Feature;

use App\Livewire\NotificationBell;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\User;
use App\Notifications\HarvestRecordedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the real-time delivery added on top of HarvestRecordObserverTest's
 * existing database-channel coverage: HarvestRecordedNotification now also
 * broadcasts over Reverb (private App.Models.User.{id} channel -- see
 * BroadcastChannelAuthorizationTest for authorization), and
 * NotificationBell.php's echo-notification listener picks that up.
 */
class NotificationBellLiveUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function harvestRecord(int $teamId): HarvestRecord
    {
        $farm = Farm::factory()->create(['team_id' => $teamId]);
        $field = Field::factory()->create(['farm_id' => $farm->id]);
        $crop = Crop::factory()->create(['team_id' => $teamId]);
        $cropCycle = CropCycle::factory()->create([
            'field_id' => $field->id,
            'crop_id' => $crop->id,
        ]);

        return HarvestRecord::factory()->create([
            'crop_cycle_id' => $cropCycle->id,
            'quantity_harvested' => 320,
            'unit' => 'kg',
        ]);
    }

    public function test_harvest_recorded_notification_broadcasts(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $record = $this->harvestRecord($owner->currentTeam->id);

        $notification = new HarvestRecordedNotification($record);

        $this->assertContains('broadcast', $notification->via($owner));
        $this->assertSame(
            $notification->toDatabase($owner),
            $notification->toBroadcast($owner)->data
        );
    }

    /**
     * #[Computed] properties cache for the component's lifetime -- a
     * notification created after the bell has already rendered must not
     * appear until the echo-notification listener (fired when Echo
     * receives a broadcast) busts that cache.
     */
    public function test_refresh_picks_up_a_notification_created_after_mount(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $component = Livewire::actingAs($owner)->test(NotificationBell::class);
        $component->assertSet('unreadCount', 0);

        $this->harvestRecord($owner->currentTeam->id);

        // Still cached at this point -- nothing has told the component to
        // recompute yet.
        $component->assertSet('unreadCount', 0);

        $component->call('refresh');

        $component->assertSet('unreadCount', 1);
    }

    /**
     * refresh() must only bust the cache, never mark anything read --
     * that's markAllAsRead()'s job on the differently named
     * 'notifications-refresh' event.
     */
    public function test_refresh_does_not_mark_anything_read(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $this->harvestRecord($owner->currentTeam->id);

        Livewire::actingAs($owner)
            ->test(NotificationBell::class)
            ->call('refresh')
            ->assertSet('unreadCount', 1);

        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
    }
}
