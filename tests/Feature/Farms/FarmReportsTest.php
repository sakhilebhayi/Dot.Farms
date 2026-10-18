<?php

namespace Tests\Feature\Farms;

use App\Enums\CropCycleStatus;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\PlantingRecord;
use App\Models\Team;
use App\Services\Farms\FarmReports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFarmWorld;
use Tests\TestCase;

class FarmReportsTest extends TestCase
{
    use BuildsFarmWorld;
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->team = $this->teamWithOwner();
    }

    private function field(?float $hectares, string $name = 'North'): Field
    {
        $farm = Farm::factory()->create(['team_id' => $this->team->id, 'name' => 'Test Farm']);

        return Field::factory()->create(['farm_id' => $farm->id, 'name' => $name, 'size_hectares' => $hectares]);
    }

    private function cycle(Field $field, string $season = '2026 Summer', ?Crop $crop = null, string $state = 'growing'): CropCycle
    {
        $crop ??= Crop::factory()->create(['team_id' => $this->team->id, 'name' => 'Maize', 'variety' => null]);

        return CropCycle::factory()->{$state}()->create(['field_id' => $field->id, 'crop_id' => $crop->id, 'season' => $season, 'planted_at' => '2026-01-10']);
    }

    private function harvest(CropCycle $cycle, float $qty, string $unit = 'kg', string $date = '2026-03-10'): HarvestRecord
    {
        return HarvestRecord::factory()->create(['crop_cycle_id' => $cycle->id, 'quantity_harvested' => $qty, 'unit' => $unit, 'harvested_at' => $date]);
    }

    private function reports(): FarmReports
    {
        return app(FarmReports::class);
    }

    public function test_yield_per_field_is_normalised_by_hectare_within_one_unit_family(): void
    {
        $cycle = $this->cycle($this->field(10));
        $this->harvest($cycle, 4000, 'kg');
        $this->harvest($cycle, 1, 't');

        $row = $this->reports()->yieldByFieldSeason($this->team)->sole();

        $this->assertSame('2026 Summer', $row['season']);
        $this->assertSame(10.0, $row['area_ha']);
        $this->assertSame('field', $row['area_basis']);
        $this->assertSame(5000.0, $row['harvested']->amountFor('weight'));
        $this->assertSame(500.0, $row['per_ha']->amountFor('weight'));
    }

    public function test_mixed_units_stay_as_separate_subtotals_and_are_never_added(): void
    {
        $cycle = $this->cycle($this->field(5));
        $this->harvest($cycle, 100, 'kg');
        $this->harvest($cycle, 20, 'crate');
        $this->harvest($cycle, 30, 'crate');

        $row = $this->reports()->yieldByFieldSeason($this->team)->sole();

        $this->assertSame(100.0, $row['harvested']->amountFor('weight'));
        $this->assertSame(50.0, $row['harvested']->amountFor('crate'));
        $this->assertSame(20.0, $row['per_ha']->amountFor('weight'));
        $this->assertSame(10.0, $row['per_ha']->amountFor('crate'));
        $this->assertCount(2, $row['harvested']->all());
    }

    public function test_planted_area_in_hectares_is_the_basis_when_recorded_otherwise_the_field_size(): void
    {
        $field = $this->field(10);
        $partial = $this->cycle($field, '2026 Summer');
        PlantingRecord::factory()->create(['crop_cycle_id' => $partial->id, 'quantity_planted' => 4, 'unit' => 'ha']);
        PlantingRecord::factory()->create(['crop_cycle_id' => $partial->id, 'quantity_planted' => 4, 'unit' => 'ha']);
        $this->harvest($partial, 1600);

        $row = $this->reports()->yieldByFieldSeason($this->team)->sole();

        $this->assertSame(8.0, $row['area_ha']);
        $this->assertSame('planted', $row['area_basis']);
        $this->assertSame(200.0, $row['per_ha']->amountFor('weight'));
    }

    public function test_no_area_means_no_per_hectare_figure_rather_than_a_made_up_one(): void
    {
        $cycle = $this->cycle($this->field(null));
        $this->harvest($cycle, 100);

        $row = $this->reports()->yieldByFieldSeason($this->team)->sole();

        $this->assertNull($row['area_ha']);
        $this->assertNull($row['area_basis']);
        $this->assertTrue($row['per_ha']->isEmpty());
        $this->assertSame(100.0, $row['harvested']->amountFor('weight'));
    }

    public function test_yield_can_be_limited_to_one_season_and_excludes_deleted_and_foreign_records(): void
    {
        $field = $this->field(10);
        $summer = $this->cycle($field, '2026 Summer');
        $winter = $this->cycle($field, '2026 Winter');
        $this->harvest($summer, 100);
        $this->harvest($winter, 300);
        $this->harvest($winter, 999)->delete();
        $foreign = $this->world($this->teamWithOwner());
        $foreign->cycle->update(['season' => '2026 Winter']);

        $all = $this->reports()->yieldByFieldSeason($this->team);
        $winterOnly = $this->reports()->yieldByFieldSeason($this->team, '2026 Winter');

        $this->assertCount(2, $all);
        $this->assertCount(1, $winterOnly);
        $this->assertSame(300.0, $winterOnly->sole()['harvested']->amountFor('weight'));
    }

    public function test_a_cycle_with_no_harvest_still_appears_with_an_empty_total(): void
    {
        $this->cycle($this->field(10), '2026 Summer', null, 'planted');

        $row = $this->reports()->yieldByFieldSeason($this->team)->sole();

        $this->assertTrue($row['harvested']->isEmpty());
        $this->assertSame(CropCycleStatus::Planted, $row['cycle']->status);
    }

    public function test_harvest_totals_per_crop_and_month(): void
    {
        $field = $this->field(10);
        $maize = Crop::factory()->create(['team_id' => $this->team->id, 'name' => 'Maize', 'variety' => null]);
        $wheat = Crop::factory()->create(['team_id' => $this->team->id, 'name' => 'Wheat', 'variety' => null]);
        $m = $this->cycle($field, '2026 Summer', $maize);
        $w = $this->cycle($field, '2026 Winter', $wheat);
        $this->harvest($m, 100, 'kg', '2026-03-01');
        $this->harvest($m, 1, 't', '2026-03-31');
        $this->harvest($m, 40, 'crate', '2026-03-15');
        $this->harvest($m, 50, 'kg', '2026-04-02');
        $this->harvest($w, 70, 'kg', '2026-03-20');
        $this->harvest($w, 5, 'kg', '2026-03-21')->delete();

        $rows = $this->reports()->harvestByCropMonth($this->team)->keyBy(fn ($r) => $r['crop'].'|'.$r['month']);

        $this->assertCount(3, $rows);
        $this->assertSame(1100.0, $rows['Maize|2026-03']['totals']->amountFor('weight'));
        $this->assertSame(40.0, $rows['Maize|2026-03']['totals']->amountFor('crate'));
        $this->assertSame(3, $rows['Maize|2026-03']['records']);
        $this->assertSame(50.0, $rows['Maize|2026-04']['totals']->amountFor('weight'));
        $this->assertSame(70.0, $rows['Wheat|2026-03']['totals']->amountFor('weight'));
        $this->assertSame('March 2026', $rows['Maize|2026-03']['label']);
    }

    public function test_harvest_by_month_is_newest_first(): void
    {
        $c = $this->cycle($this->field(1));
        $this->harvest($c, 1, 'kg', '2026-01-05');
        $this->harvest($c, 1, 'kg', '2026-05-05');
        $this->harvest($c, 1, 'kg', '2026-03-05');

        $this->assertSame(['2026-05', '2026-03', '2026-01'], $this->reports()->harvestByCropMonth($this->team)->pluck('month')->all());
    }

    public function test_outstanding_cycles_are_the_open_ones_with_what_is_already_harvested(): void
    {
        $field = $this->field(10);
        $planned = $this->cycle($field, '2027 Summer', null, 'planned');
        $overdue = $this->cycle($field, '2026 Summer', null, 'growing');
        $overdue->update(['expected_harvest_at' => now()->subDays(10)]);
        $onTrack = $this->cycle($field, '2026 Summer', null, 'planted');
        $onTrack->update(['expected_harvest_at' => now()->addDays(30)]);
        $this->cycle($field, '2025 Summer', null, 'harvested');
        $this->cycle($field, '2025 Winter', null, 'failed');
        $this->cycle($field, '2025 Spring', null, 'abandoned');
        $this->harvest($overdue, 250);

        $rows = $this->reports()->outstandingCycles($this->team);

        $this->assertEqualsCanonicalizing([$planned->id, $overdue->id, $onTrack->id], $rows->pluck('cycle.id')->all());
        $overdueRow = $rows->firstWhere('cycle.id', $overdue->id);
        $this->assertTrue($overdueRow['overdue']);
        $this->assertSame(250.0, $overdueRow['harvested']->amountFor('weight'));
        $this->assertFalse($rows->firstWhere('cycle.id', $onTrack->id)['overdue']);
        $this->assertFalse($rows->firstWhere('cycle.id', $planned->id)['overdue']);
        $this->assertSame($overdue->id, $rows->first()['cycle']->id, 'overdue cycles sort first');
    }

    public function test_harvest_total_for_a_window_counts_every_record_not_a_capped_list(): void
    {
        $cycle = $this->cycle($this->field(10));
        for ($i = 0; $i < 9; $i++) {
            $this->harvest($cycle, 100, 'kg', now()->subDays($i)->toDateString());
        }
        $this->harvest($cycle, 5000, 'kg', now()->subDays(40)->toDateString());

        $window = $this->reports()->harvestTotalSince($this->team, now()->subDays(30));

        $this->assertSame(9, $window['records']);
        $this->assertSame(900.0, $window['totals']->amountFor('weight'));
    }

    public function test_the_reports_page_renders_every_section_with_real_numbers(): void
    {
        $field = $this->field(10, 'North Paddock');
        $cycle = $this->cycle($field);
        $cycle->update(['expected_harvest_at' => now()->subDays(3)]);
        $this->harvest($cycle, 4000, 'kg', '2026-03-10');
        $this->harvest($cycle, 1, 't', '2026-03-12');

        $this->actingAs($this->team->owner)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Yield per field')
            ->assertSee('North Paddock')
            ->assertSee('5,000 kg (5 t)')
            ->assertSee('500 kg')
            ->assertSee('March 2026')
            ->assertSee('Outstanding cycles')
            ->assertSee('overdue')
            ->assertSee(route('harvests.export'), false);
    }

    public function test_the_reports_page_can_be_limited_to_a_season(): void
    {
        $field = $this->field(10, 'North Paddock');
        $this->harvest($this->cycle($field, '2026 Summer'), 111, 'kg');
        $this->harvest($this->cycle($field, '2026 Winter'), 222, 'kg');

        $page = $this->actingAs($this->team->owner)->get(route('reports.index', ['season' => '2026 Winter']))->assertOk();

        $page->assertSee('222 kg')->assertDontSee('111 kg');
    }

    public function test_empty_reports_explain_what_to_do(): void
    {
        $this->actingAs($this->team->owner)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Nothing to report yet')
            ->assertSee('record a planting')
            ->assertDontSee(route('harvests.export'), false);
    }
}
