<?php

namespace Tests\Feature;

use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The observer in App\Observers\HarvestRecordObserver must fire through
 * the real HTTP request path, not just via direct model/factory creation
 * — see docs/superpowers/specs/2026-08-09-harvest-notification-wiring-design.md.
 */
class HarvestRecordControllerNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_a_harvest_through_the_controller_notifies_the_team(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $secondMember = User::factory()->create();
        $team->users()->attach($secondMember, ['role' => 'admin']);

        $farm = Farm::factory()->create(['team_id' => $team->id]);
        $field = Field::factory()->create(['farm_id' => $farm->id]);
        $crop = Crop::factory()->create(['team_id' => $team->id]);
        $cropCycle = CropCycle::factory()->create([
            'field_id' => $field->id,
            'crop_id' => $crop->id,
        ]);

        $this->actingAs($owner)->post(
            route('crop-cycles.harvest.store', [$farm, $field, $cropCycle]),
            [
                'harvested_at' => now()->toDateString(),
                'quantity_harvested' => 500,
                'unit' => 'kg',
            ]
        )->assertRedirect();

        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $secondMember->fresh()->unreadNotifications()->count());
    }
}
