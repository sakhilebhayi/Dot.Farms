<?php

namespace Tests\Feature\Analytics;

use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\Team;
use App\Models\User;
use App\Services\Analytics\MetricsCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The aggregate numbers one company publishes to Dot.Analytics. Pure queries, always scoped to the company
 * explicitly (the team scopes apply to whoever is signed in), in UTC, never counting deleted rows, and never
 * adding quantities in units that cannot be converted into each other.
 */
class MetricsCollectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 12:00:00'); // UTC
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function company(): Team
    {
        $owner = User::factory()->create();

        return Team::forceCreate(['user_id' => $owner->id, 'name' => 'Company '.uniqid(), 'personal_team' => false, 'infodot_team_uuid' => (string) Str::uuid()]);
    }

    private function field(Team $team): Field
    {
        $farm = Farm::factory()->create(['team_id' => $team->id]);

        return Field::factory()->create(['farm_id' => $farm->id]);
    }

    private function cycle(Team $team, string $state = 'growing', ?Field $field = null): CropCycle
    {
        return CropCycle::factory()->{$state}()->create([
            'field_id' => ($field ?? $this->field($team))->id,
            'crop_id' => Crop::factory()->create(['team_id' => $team->id])->id,
        ]);
    }

    private function harvest(CropCycle $cycle, float|int|string $quantity, string $unit = 'kg', mixed $at = null): HarvestRecord
    {
        return HarvestRecord::factory()->create([
            'crop_cycle_id' => $cycle->id,
            'quantity_harvested' => $quantity,
            'unit' => $unit,
            'harvested_at' => $at ?? now()->subDay(),
        ]);
    }

    /** @return array<string, int|float> */
    private function collect(Team $team): array
    {
        return app(MetricsCollector::class)->collect($team);
    }

    public function test_it_reports_every_key_analytics_defines_for_farms(): void
    {
        $this->assertSame(['active_cycles', 'harvests_30d', 'harvested_kg_30d'], array_keys($this->collect($this->company())));
    }

    public function test_a_company_with_no_farms_reports_real_zeros(): void
    {
        $this->assertSame(['active_cycles' => 0, 'harvests_30d' => 0, 'harvested_kg_30d' => 0.0], $this->collect($this->company()));
    }

    // ------------------------------------------------------------- active_cycles

    public function test_active_cycles_are_the_crops_in_the_ground_planted_or_growing(): void
    {
        $team = $this->company();
        foreach (['planted', 'growing', 'growing', 'planned', 'harvested', 'failed', 'abandoned'] as $state) {
            $this->cycle($team, $state);
        }

        $this->assertSame(3, $this->collect($team)['active_cycles']);
    }

    public function test_active_cycles_leave_out_deleted_cycles_and_cycles_under_deleted_fields_or_farms(): void
    {
        $team = $this->company();
        $this->cycle($team);
        $this->cycle($team)->delete();
        $deletedField = $this->field($team);
        $this->cycle($team, 'growing', $deletedField);
        DB::table('fields')->where('id', $deletedField->id)->update(['deleted_at' => now()]); // as if a cascade had missed the cycle
        $deletedFarmField = $this->field($team);
        $this->cycle($team, 'growing', $deletedFarmField);
        DB::table('farms')->where('id', $deletedFarmField->farm_id)->update(['deleted_at' => now()]);

        $this->assertSame(1, $this->collect($team)['active_cycles']);
    }

    // ------------------------------------------------------------- harvests_30d

    public function test_harvests_30d_window_includes_exactly_thirty_days_back_and_excludes_anything_older_or_later_than_now(): void
    {
        $team = $this->company();
        $cycle = $this->cycle($team);
        $this->harvest($cycle, 1, 'kg', now());                                     // now: in
        $this->harvest($cycle, 1, 'kg', now()->subDays(30));                        // exactly 30 days ago: in
        $this->harvest($cycle, 1, 'kg', now()->subDays(30)->subSecond());           // a second older: out
        $this->harvest($cycle, 1, 'kg', now()->subDays(90));                        // out
        $this->harvest($cycle, 1, 'kg', now()->addDay());                           // not yet happened: out

        $collected = $this->collect($team);

        $this->assertSame(2, $collected['harvests_30d']);
        $this->assertSame(2.0, $collected['harvested_kg_30d']);
    }

    public function test_harvests_30d_counts_every_unit_but_not_deleted_records_or_records_under_deleted_parents(): void
    {
        $team = $this->company();
        $cycle = $this->cycle($team);
        $this->harvest($cycle, 5, 'kg');
        $this->harvest($cycle, 40, 'crate');
        $this->harvest($cycle, 3, 'bag');
        $this->harvest($cycle, 9, 'kg')->delete();
        $deadCycle = $this->cycle($team);
        $this->harvest($deadCycle, 7, 'kg');
        $deadCycle->delete(); // cascades to its harvests
        $orphanField = $this->field($team);
        $this->harvest($this->cycle($team, 'growing', $orphanField), 7, 'kg');
        DB::table('farms')->where('id', $orphanField->farm_id)->update(['deleted_at' => now()]);

        $this->assertSame(3, $this->collect($team)['harvests_30d']);
    }

    public function test_the_window_moves_with_the_clock(): void
    {
        $team = $this->company();
        $this->harvest($this->cycle($team), 1, 'kg', '2026-10-06 12:00:00');

        $this->assertSame(1, $this->collect($team)['harvests_30d']);
        Carbon::setTestNow('2026-11-05 12:00:00'); // 30 days to the second
        $this->assertSame(1, $this->collect($team)['harvests_30d']);
        Carbon::setTestNow('2026-11-05 12:00:01');
        $this->assertSame(0, $this->collect($team)['harvests_30d']);
    }

    // ------------------------------------------------------------- harvested_kg_30d

    public function test_kilograms_and_tonnes_are_added_in_kilograms(): void
    {
        $team = $this->company();
        $cycle = $this->cycle($team);
        $this->harvest($cycle, 100, 'kg');
        $this->harvest($cycle, 2, 't');
        $this->harvest($cycle, '50.50', 'kg');
        $this->harvest($cycle, '0.25', 't');

        $this->assertSame(2400.5, $this->collect($team)['harvested_kg_30d']);
    }

    public function test_units_that_cannot_be_converted_to_kilograms_are_never_added(): void
    {
        $team = $this->company();
        $cycle = $this->cycle($team);
        $this->harvest($cycle, 100, 'kg');
        foreach (['bag' => 40, 'crate' => 500, 'bunch' => 12, 'l' => 300, 'head' => 70, 'Kilos' => 999, 'KG' => 888] as $unit => $quantity) {
            $this->harvest($cycle, $quantity, $unit);
        }

        $collected = $this->collect($team);

        $this->assertSame(100.0, $collected['harvested_kg_30d'], 'bags, crates, litres, heads and unrecognised spellings are not kilograms');
        $this->assertSame(8, $collected['harvests_30d'], 'but each is still a harvest');
    }

    public function test_only_weight_in_the_window_counts_and_deleted_weight_does_not(): void
    {
        $team = $this->company();
        $cycle = $this->cycle($team);
        $this->harvest($cycle, 10, 'kg');
        $this->harvest($cycle, 1000, 'kg', now()->subDays(31));
        $this->harvest($cycle, 5000, 'kg')->delete();

        $this->assertSame(10.0, $this->collect($team)['harvested_kg_30d']);
    }

    public function test_decimal_quantities_are_rounded_to_two_places_without_float_noise(): void
    {
        $team = $this->company();
        $cycle = $this->cycle($team);
        $this->harvest($cycle, '0.10', 'kg');
        $this->harvest($cycle, '0.20', 'kg');

        $this->assertSame(0.3, $this->collect($team)['harvested_kg_30d']);
    }

    public function test_the_window_is_the_same_instant_whatever_zone_the_clock_is_expressed_in(): void
    {
        $team = $this->company();
        $this->harvest($this->cycle($team), 1, 'kg', now()->subDays(30));

        $johannesburg = Carbon::parse('2026-11-05 14:00:00', 'Africa/Johannesburg'); // 12:00 UTC

        $this->assertSame(1, app(MetricsCollector::class)->collect($team, $johannesburg)['harvests_30d']);
        $this->assertSame(0, app(MetricsCollector::class)->collect($team, $johannesburg->copy()->addSecond())['harvests_30d']);
    }

    // ------------------------------------------------------------- isolation

    public function test_company_a_numbers_never_include_company_b_rows(): void
    {
        $a = $this->company();
        $b = $this->company();
        $this->harvest($this->cycle($a), 10, 'kg');
        $cycleB = $this->cycle($b);
        foreach (range(1, 4) as $i) {
            $this->harvest($cycleB, 100, 'kg');
        }
        $this->cycle($b);

        $this->assertSame(['active_cycles' => 1, 'harvests_30d' => 1, 'harvested_kg_30d' => 10.0], $this->collect($a));
        $this->assertSame(['active_cycles' => 2, 'harvests_30d' => 4, 'harvested_kg_30d' => 400.0], $this->collect($b));
    }

    public function test_the_numbers_are_the_same_whoever_is_signed_in_or_nobody(): void
    {
        $a = $this->company();
        $b = $this->company();
        $this->harvest($this->cycle($a), 10, 'kg');
        $this->harvest($this->cycle($b), 20, 'kg');
        $this->harvest($this->cycle($b), 20, 'kg');
        $b->owner->forceFill(['current_team_id' => $b->id])->save();

        $anonymous = $this->collect($a);
        $this->actingAs($b->owner);

        $this->assertSame(['active_cycles' => 1, 'harvests_30d' => 1, 'harvested_kg_30d' => 10.0], $anonymous);
        $this->assertSame($anonymous, $this->collect($a), 'the signed-in user\'s own team scope must not change another company\'s numbers');
        $this->assertSame(2, $this->collect($b)['harvests_30d']);
        $this->assertSame(2, HarvestRecord::query()->count(), 'sanity: the signed-in scope really is on for ordinary queries');
    }
}
