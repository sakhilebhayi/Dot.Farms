<?php

namespace Tests\Feature\Farms;

use App\Models\HarvestRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFarmWorld;
use Tests\TestCase;

class DashboardHarvestTotalTest extends TestCase
{
    use BuildsFarmWorld;
    use RefreshDatabase;

    public function test_the_harvest_tile_totals_every_harvest_in_the_window_not_the_six_row_list(): void
    {
        $team = $this->teamWithOwner();
        $w = $this->world($team);
        $w->harvest->delete();
        for ($i = 1; $i <= 8; $i++) {
            HarvestRecord::factory()->create(['crop_cycle_id' => $w->cycle->id, 'quantity_harvested' => 100, 'unit' => 'kg', 'harvested_at' => now()->subDays($i)]);
        }
        HarvestRecord::factory()->create(['crop_cycle_id' => $w->cycle->id, 'quantity_harvested' => 9000, 'unit' => 'kg', 'harvested_at' => now()->subDays(45)]);

        $page = $this->actingAs($team->owner)->get(route('dashboard'))->assertOk();

        $page->assertSee('Harvested, last 30 days')
            ->assertSee('800 kg')
            ->assertSee('8 harvest records')
            ->assertDontSee('9,800 kg');
    }

    public function test_mixed_units_are_shown_as_separate_figures(): void
    {
        $team = $this->teamWithOwner();
        $w = $this->world($team);
        $w->harvest->update(['quantity_harvested' => 2, 'unit' => 't', 'harvested_at' => now()->subDay()]);
        HarvestRecord::factory()->create(['crop_cycle_id' => $w->cycle->id, 'quantity_harvested' => 40, 'unit' => 'crate', 'harvested_at' => now()->subDay()]);

        $this->actingAs($team->owner)->get(route('dashboard'))
            ->assertSee('2,000 kg (2 t)')
            ->assertSee('40 crate');
    }

    public function test_an_empty_window_says_so_instead_of_showing_a_zero(): void
    {
        $team = $this->teamWithOwner();

        $this->actingAs($team->owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Nothing harvested in the last 30 days')
            ->assertSee('No harvests recorded yet');
    }

    public function test_the_crops_in_season_tile_counts_overdue_cycles(): void
    {
        $team = $this->teamWithOwner();
        $w = $this->world($team);
        $w->cycle->update(['expected_harvest_at' => now()->subDays(5)]);

        $this->actingAs($team->owner)->get(route('dashboard'))->assertSee('1 past expected harvest');
    }
}
