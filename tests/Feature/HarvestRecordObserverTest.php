<?php

namespace Tests\Feature;

use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\User;
use App\Notifications\HarvestRecordedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HarvestRecordObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_harvest_record_notifies_every_team_member(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $secondMember = User::factory()->create();
        $team->users()->attach($secondMember, ['role' => 'admin']);

        $this->createHarvestRecord($team->id);

        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $secondMember->fresh()->unreadNotifications()->count());
    }

    public function test_a_harvest_inside_a_transaction_that_rolls_back_notifies_nobody(): void
    {
        Notification::fake();
        $owner = User::factory()->withPersonalTeam()->create();

        try {
            DB::transaction(function () use ($owner) {
                $this->createHarvestRecord($owner->currentTeam->id);

                throw new \RuntimeException('later step failed');
            });
        } catch (\RuntimeException) {
        }

        Notification::assertNothingSent();
        $this->assertSame(0, HarvestRecord::count());
    }

    public function test_a_harvest_in_a_transaction_that_commits_notifies_after_the_commit(): void
    {
        Notification::fake();
        $owner = User::factory()->withPersonalTeam()->create();

        DB::transaction(function () use ($owner) {
            $this->createHarvestRecord($owner->currentTeam->id);

            Notification::assertNothingSent();
        });

        Notification::assertSentTo($owner, HarvestRecordedNotification::class);
    }

    public function test_a_personal_team_with_one_member_gets_exactly_one_notification(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $this->createHarvestRecord($owner->currentTeam->id);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
    }

    public function test_a_member_of_a_different_team_is_not_notified(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $otherTeamUser = User::factory()->withPersonalTeam()->create();

        $this->createHarvestRecord($owner->currentTeam->id);

        $this->assertSame(0, $otherTeamUser->fresh()->unreadNotifications()->count());
    }

    public function test_the_notification_payload_references_the_created_harvest_record(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $harvestRecord = $this->createHarvestRecord($owner->currentTeam->id);

        $notification = $owner->fresh()->notifications()->first();

        $this->assertSame('harvest_recorded', $notification->data['type']);
        $this->assertSame($harvestRecord->id, $notification->data['harvest_record_id']);
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
