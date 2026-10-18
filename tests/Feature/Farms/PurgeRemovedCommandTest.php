<?php

namespace Tests\Feature\Farms;

use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\PlantingRecord;
use App\Models\RecordRestoration;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\BuildsFarmWorld;
use Tests\TestCase;

/**
 * farms:purge-removed hard-deletes what has been removed for longer than the
 * retention window (REMOVED_RETENTION_DAYS, default 90), children with their
 * parents, and nothing that is still live.
 */
class PurgeRemovedCommandTest extends TestCase
{
    use BuildsFarmWorld;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-18 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function removeAgo(int $days, object ...$models): void
    {
        $at = now()->subDays($days);
        foreach ($models as $model) {
            $model::withoutGlobalScopes()->whereKey($model->id)->update(['deleted_at' => $at]);
        }
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $out = [];
        foreach (['farms', 'fields', 'crops', 'crop_cycles', 'planting_records', 'harvest_records'] as $table) {
            $out[$table] = DB::table($table)->count();
        }

        return $out;
    }

    public function test_the_default_retention_is_ninety_days(): void
    {
        $this->assertSame(90, config('farms.removed_retention_days'));
    }

    public function test_it_is_scheduled_daily(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'farms:purge-removed'));

        $this->assertNotNull($event, 'farms:purge-removed is not scheduled');
        $this->assertSame('10 3 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_a_chain_removed_more_than_the_retention_window_ago_is_purged_whole(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->delete();
        $this->removeAgo(91, $w->farm, $w->field, $w->cycle, $w->planting, $w->harvest);

        $this->artisan('farms:purge-removed')->assertSuccessful();

        $this->assertSame(['farms' => 0, 'fields' => 0, 'crops' => 1, 'crop_cycles' => 0, 'planting_records' => 0, 'harvest_records' => 0], $this->counts());
    }

    public function test_the_boundary_is_strictly_older_than_the_window(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->delete();
        $this->removeAgo(90, $w->farm, $w->field, $w->cycle, $w->planting, $w->harvest);

        $this->artisan('farms:purge-removed')->assertSuccessful();
        $this->assertSame(1, $this->counts()['farms']);

        Carbon::setTestNow(now()->addSecond());
        $this->artisan('farms:purge-removed')->assertSuccessful();
        $this->assertSame(0, $this->counts()['farms']);
    }

    public function test_recently_removed_records_and_live_records_are_never_touched(): void
    {
        $old = $this->world($this->teamWithOwner());
        $recent = $this->world($this->teamWithOwner());
        $live = $this->world($this->teamWithOwner());
        $old->farm->delete();
        $this->removeAgo(120, $old->farm, $old->field, $old->cycle, $old->planting, $old->harvest);
        $recent->farm->delete();
        $this->removeAgo(10, $recent->farm, $recent->field, $recent->cycle, $recent->planting, $recent->harvest);

        $liveBefore = [
            Farm::withoutGlobalScopes()->whereKey($live->farm->id)->first()->toArray(),
            HarvestRecord::withoutGlobalScopes()->whereKey($live->harvest->id)->first()->toArray(),
        ];

        $this->artisan('farms:purge-removed')->assertSuccessful();

        $this->assertSame(2, $this->counts()['farms']);
        $this->assertSame(2, $this->counts()['harvest_records']);
        $this->assertSame($liveBefore, [
            Farm::withoutGlobalScopes()->whereKey($live->farm->id)->first()->toArray(),
            HarvestRecord::withoutGlobalScopes()->whereKey($live->harvest->id)->first()->toArray(),
        ]);
        $this->assertSoftDeleted($recent->farm);
        $this->assertSoftDeleted($recent->harvest);
    }

    public function test_a_single_old_removed_record_is_purged_without_its_live_siblings(): void
    {
        $w = $this->world($this->teamWithOwner());
        $extra = HarvestRecord::factory()->create(['crop_cycle_id' => $w->cycle->id, 'unit' => 'kg', 'harvested_at' => now()->subDay()]);
        $w->harvest->delete();
        $this->removeAgo(200, $w->harvest);

        $this->artisan('farms:purge-removed')->assertSuccessful();

        $this->assertDatabaseMissing('harvest_records', ['id' => $w->harvest->id]);
        $this->assertDatabaseHas('harvest_records', ['id' => $extra->id, 'deleted_at' => null]);
    }

    public function test_an_expired_parent_with_a_live_child_is_kept_and_the_live_child_untouched(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->delete();
        $this->removeAgo(120, $w->farm, $w->field, $w->cycle, $w->planting, $w->harvest);
        PlantingRecord::withoutGlobalScopes()->whereKey($w->planting->id)->update(['deleted_at' => null]);

        $this->artisan('farms:purge-removed')->assertSuccessful();

        $this->assertDatabaseHas('farms', ['id' => $w->farm->id]);
        $this->assertDatabaseHas('fields', ['id' => $w->field->id]);
        $this->assertDatabaseHas('crop_cycles', ['id' => $w->cycle->id]);
        $this->assertDatabaseHas('planting_records', ['id' => $w->planting->id, 'deleted_at' => null]);
        $this->assertDatabaseMissing('harvest_records', ['id' => $w->harvest->id]);
    }

    public function test_an_expired_parent_is_kept_when_only_a_cycle_or_a_field_beneath_it_is_live(): void
    {
        $liveCycle = $this->world($this->teamWithOwner());
        $liveCycle->farm->delete();
        $this->removeAgo(120, $liveCycle->farm, $liveCycle->field, $liveCycle->planting, $liveCycle->harvest);
        CropCycle::withoutGlobalScopes()->whereKey($liveCycle->cycle->id)->update(['deleted_at' => null]);

        $liveField = $this->world($this->teamWithOwner());
        $liveField->farm->delete();
        $this->removeAgo(120, $liveField->farm, $liveField->cycle, $liveField->planting, $liveField->harvest);
        Field::withoutGlobalScopes()->whereKey($liveField->field->id)->update(['deleted_at' => null]);

        $this->artisan('farms:purge-removed')->assertSuccessful();

        foreach ([$liveCycle, $liveField] as $w) {
            $this->assertDatabaseHas('farms', ['id' => $w->farm->id]);
            $this->assertDatabaseHas('fields', ['id' => $w->field->id]);
        }
        $this->assertDatabaseHas('crop_cycles', ['id' => $liveCycle->cycle->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('fields', ['id' => $liveField->field->id, 'deleted_at' => null]);
    }

    public function test_a_newer_removed_descendant_goes_with_an_expired_parent(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->delete();
        $this->removeAgo(120, $w->farm, $w->field);
        $this->removeAgo(5, $w->cycle, $w->planting, $w->harvest);

        $this->artisan('farms:purge-removed')->assertSuccessful();

        $this->assertSame(['farms' => 0, 'fields' => 0, 'crops' => 1, 'crop_cycles' => 0, 'planting_records' => 0, 'harvest_records' => 0], $this->counts());
    }

    public function test_a_removed_crop_is_purged_only_when_no_cycle_history_still_names_it(): void
    {
        $w = $this->world($this->teamWithOwner());
        $unused = Crop::factory()->create(['team_id' => $w->team->id]);
        $unused->delete();
        $this->removeAgo(200, $unused);

        $w->cycle->delete();
        $other = Crop::factory()->create(['team_id' => $w->team->id]);
        CropCycle::withoutGlobalScopes()->whereKey($w->cycle->id)->update(['crop_id' => $other->id]);
        $other->delete();
        $this->removeAgo(200, $other);
        $this->removeAgo(20, $w->cycle, $w->planting, $w->harvest);

        $this->artisan('farms:purge-removed')->assertSuccessful();

        $this->assertDatabaseMissing('crops', ['id' => $unused->id]);
        $this->assertDatabaseHas('crops', ['id' => $other->id]);
        $this->assertDatabaseHas('crop_cycles', ['id' => $w->cycle->id]);

        Carbon::setTestNow(now()->addDays(100));
        $this->artisan('farms:purge-removed')->assertSuccessful();
        $this->assertDatabaseMissing('crops', ['id' => $other->id]);
        $this->assertDatabaseMissing('crop_cycles', ['id' => $w->cycle->id]);
    }

    public function test_a_dry_run_reports_and_changes_nothing(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->delete();
        $this->removeAgo(120, $w->farm, $w->field, $w->cycle, $w->planting, $w->harvest);
        $before = $this->counts();

        $this->artisan('farms:purge-removed', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run, nothing deleted. Would purge 1 farm, 1 field')
            ->assertSuccessful();

        $this->assertSame($before, $this->counts());
    }

    public function test_it_is_idempotent(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->delete();
        $this->removeAgo(120, $w->farm, $w->field, $w->cycle, $w->planting, $w->harvest);

        $this->artisan('farms:purge-removed')->assertSuccessful();
        $after = $this->counts();
        $this->artisan('farms:purge-removed')->expectsOutputToContain('Nothing to purge')->assertSuccessful();

        $this->assertSame($after, $this->counts());
    }

    public function test_it_logs_a_summary_with_the_counts_and_cutoff(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->delete();
        $this->removeAgo(120, $w->farm, $w->field, $w->cycle, $w->planting, $w->harvest);

        Log::shouldReceive('info')->once()->with('farms.purge-removed', \Mockery::on(
            fn (array $c) => $c['dry_run'] === false
                && $c['retention_days'] === 90
                && $c['cutoff'] === '2026-07-20T12:00:00+00:00'
                && $c['purged'] === ['farms' => 1, 'fields' => 1, 'crops' => 0, 'crop_cycles' => 1, 'planting_records' => 1, 'harvest_records' => 1],
        ));

        $this->artisan('farms:purge-removed')->assertSuccessful();
    }

    public function test_the_retention_window_is_configurable_and_a_nonsense_value_is_refused(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->delete();
        $this->removeAgo(40, $w->farm, $w->field, $w->cycle, $w->planting, $w->harvest);

        $this->artisan('farms:purge-removed')->assertSuccessful();
        $this->assertSame(1, $this->counts()['farms']);

        config(['farms.removed_retention_days' => 30]);
        $this->artisan('farms:purge-removed')->assertSuccessful();
        $this->assertSame(0, $this->counts()['farms']);

        $w2 = $this->world($this->teamWithOwner());
        $w2->farm->delete();
        $this->removeAgo(400, $w2->farm, $w2->field, $w2->cycle, $w2->planting, $w2->harvest);
        foreach ([0, -5] as $bad) {
            config(['farms.removed_retention_days' => $bad]);
            $this->artisan('farms:purge-removed')->assertFailed();
        }
        $this->assertSame(1, $this->counts()['farms']);
    }

    public function test_restoration_audit_rows_outlive_the_purge(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->delete();
        $this->actingAs($w->team->owner)->post(route('removed.restore', ['type' => 'farm', 'id' => $w->farm->id]));
        $w->farm->refresh()->delete();
        $this->removeAgo(120, $w->farm, $w->field, $w->cycle, $w->planting, $w->harvest);

        $this->artisan('farms:purge-removed')->assertSuccessful();

        $this->assertSame(0, $this->counts()['farms']);
        $this->assertSame(1, RecordRestoration::count());
    }
}
