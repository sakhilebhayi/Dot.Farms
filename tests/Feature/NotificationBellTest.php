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

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_notification_bell_for_authenticated_user(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeLivewire('notification-bell');
    }

    public function test_unread_count_reflects_database_notifications(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $harvestRecord = $this->createHarvestRecord($team->id);

        $user->notify(new HarvestRecordedNotification($harvestRecord));

        $this->assertDatabaseCount('notifications', 1);

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->assertSet('open', false)
            ->call('toggle')
            ->assertSet('open', true)
            ->assertSee('Harvest recorded');

        $this->assertEquals(1, $user->fresh()->unreadNotifications()->count());
    }

    public function test_mark_all_as_read_clears_unread_count(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $harvestRecord = $this->createHarvestRecord($team->id);

        $user->notify(new HarvestRecordedNotification($harvestRecord));

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('markAllAsRead');

        $this->assertEquals(0, $user->fresh()->unreadNotifications()->count());
    }

    private function createHarvestRecord(int $teamId): HarvestRecord
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
}
