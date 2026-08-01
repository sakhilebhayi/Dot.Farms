<?php

namespace Tests\Feature\Farms;

use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_shows_farm_summary(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $farm = Farm::factory()->create(['team_id' => $team->id]);
        $field = Field::factory()->create(['farm_id' => $farm->id, 'status' => 'active']);
        $crop = Crop::factory()->create(['team_id' => $team->id, 'name' => 'Maize']);
        $cropCycle = CropCycle::factory()->create([
            'field_id' => $field->id,
            'crop_id' => $crop->id,
            'status' => CropCycle::STATUS_GROWING,
        ]);
        HarvestRecord::factory()->create([
            'crop_cycle_id' => $cropCycle->id,
            'quantity_harvested' => 500,
            'unit' => 'kg',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('farmCount', 1)
            ->assertViewHas('activeFieldCount', 1)
            ->assertViewHas('cropsInSeasonCount', 1);
    }
}
