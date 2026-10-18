<?php

namespace Tests\Feature\Farms;

use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFarmWorld;
use Tests\TestCase;

class HarvestExportTest extends TestCase
{
    use BuildsFarmWorld;
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->team = $this->teamWithOwner();
    }

    private function cycle(string $season = '2026 Summer', string $crop = 'Maize'): CropCycle
    {
        $farm = Farm::factory()->create(['team_id' => $this->team->id, 'name' => 'Vale Farm']);
        $field = Field::factory()->create(['farm_id' => $farm->id, 'name' => 'North', 'code' => 'N-'.uniqid()]);
        $crop = Crop::factory()->create(['team_id' => $this->team->id, 'name' => $crop, 'variety' => 'Yellow']);

        return CropCycle::factory()->growing()->create(['field_id' => $field->id, 'crop_id' => $crop->id, 'season' => $season, 'planted_at' => '2026-01-01']);
    }

    private function harvest(CropCycle $cycle, array $attrs = []): HarvestRecord
    {
        return HarvestRecord::factory()->create(['crop_cycle_id' => $cycle->id, 'harvested_at' => '2026-03-10', 'quantity_harvested' => 100, 'unit' => 'kg', 'quality_grade' => 'A', 'notes' => null, ...$attrs]);
    }

    /** @return list<array<string, string>> */
    private function csv(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $rows = array_map(fn ($line) => str_getcsv($line, ',', '"', ''), array_filter(explode("\n", $content), 'strlen'));
        $header = array_shift($rows);

        return array_map(fn ($row) => array_combine($header, $row), $rows);
    }

    public function test_it_downloads_a_csv_with_one_row_per_harvest_and_a_stable_header(): void
    {
        $cycle = $this->cycle();
        $a = $this->harvest($cycle, ['quantity_harvested' => 1.5, 'unit' => 't', 'harvested_at' => '2026-03-12']);
        $b = $this->harvest($cycle, ['quantity_harvested' => 20, 'unit' => 'crate', 'quality_grade' => 'B', 'harvested_at' => '2026-03-10']);

        $response = $this->actingAs($this->team->owner)->get(route('harvests.export'))->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('dot-farms-harvests-', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.csv', $response->headers->get('Content-Disposition'));

        $rows = $this->csv($response->streamedContent());

        $this->assertSame(
            ['harvest_id', 'harvested_on', 'farm', 'field_code', 'field', 'crop', 'variety', 'season', 'quantity', 'unit', 'quantity_kg', 'quality_grade', 'notes', 'recorded_at'],
            array_keys($rows[0])
        );
        $this->assertCount(2, $rows);
        $this->assertSame((string) $b->id, $rows[0]['harvest_id'], 'oldest harvest first');
        $this->assertSame('2026-03-10', $rows[0]['harvested_on']);
        $this->assertSame('Vale Farm', $rows[0]['farm']);
        $this->assertSame('Maize', $rows[0]['crop']);
        $this->assertSame('crate', $rows[0]['unit']);
        $this->assertSame('', $rows[0]['quantity_kg'], 'a crate has no weight, so no kg is invented');
        $this->assertSame('1500', $rows[1]['quantity_kg']);
        $this->assertSame((string) $a->id, $rows[1]['harvest_id']);
    }

    public function test_it_honours_the_same_filters_as_the_harvest_list(): void
    {
        $summer = $this->cycle('2026 Summer');
        $winter = $this->cycle('2026 Winter', 'Wheat');
        $this->harvest($summer);
        $this->harvest($winter, ['harvested_at' => '2026-07-01']);

        $rows = $this->csv($this->actingAs($this->team->owner)->get(route('harvests.export', ['season' => '2026 Winter']))->streamedContent());
        $this->assertCount(1, $rows);
        $this->assertSame('Wheat', $rows[0]['crop']);

        $rows = $this->csv($this->actingAs($this->team->owner)->get(route('harvests.export', ['from' => '2026-06-01']))->streamedContent());
        $this->assertCount(1, $rows);

        $rows = $this->csv($this->actingAs($this->team->owner)->get(route('harvests.export', ['crop_id' => $summer->crop_id]))->streamedContent());
        $this->assertSame('Maize', $rows[0]['crop']);
    }

    public function test_it_leaves_out_deleted_records_and_other_teams(): void
    {
        $cycle = $this->cycle();
        $keep = $this->harvest($cycle);
        $this->harvest($cycle)->delete();
        $foreign = $this->world($this->teamWithOwner());
        $foreign->farm->update(['name' => 'FOREIGN-FARM']);

        $content = $this->actingAs($this->team->owner)->get(route('harvests.export'))->streamedContent();

        $this->assertCount(1, $this->csv($content));
        $this->assertStringNotContainsString('FOREIGN-FARM', $content);
        $this->assertStringContainsString((string) $keep->id, $content);
    }

    public function test_spreadsheet_formulas_in_text_cells_are_neutralised(): void
    {
        $cycle = $this->cycle();
        $this->harvest($cycle, ['notes' => '=HYPERLINK("http://evil.example","click")', 'quality_grade' => '+A']);

        $row = $this->csv($this->actingAs($this->team->owner)->get(route('harvests.export'))->streamedContent())[0];

        $this->assertSame('\'=HYPERLINK("http://evil.example","click")', $row['notes']);
        $this->assertSame("'+A", $row['quality_grade']);
    }

    public function test_notes_with_commas_quotes_and_newlines_round_trip(): void
    {
        $cycle = $this->cycle();
        $this->harvest($cycle, ['notes' => "Dry, \"early\" pick\nsecond line"]);

        $content = $this->actingAs($this->team->owner)->get(route('harvests.export'))->streamedContent();
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, preg_replace('/^\xEF\xBB\xBF/', '', $content));
        rewind($handle);
        fgetcsv($handle, null, ',', '"', '');
        $row = fgetcsv($handle, null, ',', '"', '');

        $this->assertContains("Dry, \"early\" pick\nsecond line", $row);
    }

    public function test_any_team_member_can_export_and_an_outsider_cannot(): void
    {
        $this->harvest($this->cycle());

        $this->actingAs($this->memberOf($this->team, 'viewer'))->get(route('harvests.export'))->assertOk();
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get(route('harvests.export'))->assertRedirect();
    }

    public function test_the_harvest_list_links_to_the_export_with_its_filters_and_shows_totals(): void
    {
        $cycle = $this->cycle('2026 Summer');
        $this->harvest($cycle, ['quantity_harvested' => 2, 'unit' => 't']);
        $this->harvest($cycle, ['quantity_harvested' => 500, 'unit' => 'kg']);
        $this->harvest($cycle, ['quantity_harvested' => 7, 'unit' => 'crate']);

        $page = $this->actingAs($this->team->owner)->get(route('harvests.index', ['season' => '2026 Summer']))->assertOk();

        $page->assertSee('2,500 kg (2.5 t)')->assertSee('7 crate')->assertSee('3 records');
        $page->assertSee(e(route('harvests.export', ['season' => '2026 Summer'])), false);
    }

    public function test_the_harvest_list_filters_by_season(): void
    {
        $this->harvest($this->cycle('2026 Summer'), ['quantity_harvested' => 48271]);
        $this->harvest($this->cycle('2026 Winter', 'Wheat'), ['quantity_harvested' => 53907]);

        $this->actingAs($this->team->owner)->get(route('harvests.index', ['season' => '2026 Winter']))
            ->assertOk()->assertSee('Wheat')->assertDontSee('48271');
    }

    public function test_the_empty_harvest_list_explains_how_a_harvest_gets_recorded(): void
    {
        $this->actingAs($this->team->owner)->get(route('harvests.index'))
            ->assertOk()
            ->assertSee('No harvests recorded yet')
            ->assertSee('plan a cycle')
            ->assertDontSee(route('harvests.export'), false);
    }
}
