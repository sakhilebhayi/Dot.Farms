<?php

namespace Tests\Feature\Farms;

use App\Enums\CropCycleStatus;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\PlantingRecord;
use App\Models\RecordRestoration;
use App\Notifications\HarvestRecordedNotification;
use App\Services\Farms\CropCycleLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsFarmWorld;
use Tests\TestCase;

/**
 * Restoring removed records: who may, what is refused (a child whose parent is
 * still removed), what comes back together, and what is re-derived afterwards.
 */
class RestoreRecordsTest extends TestCase
{
    use BuildsFarmWorld;
    use RefreshDatabase;

    private function restore(object $w, string $type, int $id, string $role = 'owner')
    {
        return $this->actingAs($this->memberOf($w->team, $role))
            ->post(route('removed.restore', ['type' => $type, 'id' => $id]));
    }

    private function assertRefused($response, string ...$phrases): void
    {
        $response->assertSessionHasErrors('restore');
        $message = app('session.store')->get('errors')->first('restore');
        foreach ($phrases as $phrase) {
            $this->assertStringContainsString($phrase, $message);
        }
    }

    public function test_the_owner_can_restore_each_kind_of_record_once_its_parents_are_live(): void
    {
        $w = $this->world($this->teamWithOwner());
        $extraCrop = Crop::factory()->create(['team_id' => $w->team->id]);
        $extraCrop->delete();
        foreach ([$w->harvest, $w->planting, $w->cycle, $w->field, $w->farm] as $hours => $model) {
            Carbon::setTestNow(now()->startOfDay()->addHours($hours * 3));
            $model->delete();
        }
        Carbon::setTestNow();

        foreach ([['farm', $w->farm], ['field', $w->field], ['cycle', $w->cycle], ['planting', $w->planting], ['harvest', $w->harvest], ['crop', $extraCrop]] as [$type, $model]) {
            $this->restore($w, $type, $model->id)->assertRedirect(route('removed.index'));
            $this->assertNull($model::withoutGlobalScopes()->find($model->id)->deleted_at, "$type was not restored");
        }
    }

    public function test_restoring_a_farm_brings_back_what_was_removed_with_it_and_only_that(): void
    {
        $w = $this->world($this->teamWithOwner());
        $earlier = PlantingRecord::factory()->create(['crop_cycle_id' => $w->cycle->id, 'planted_at' => now()->subMonths(2), 'unit' => 'kg']);

        Carbon::setTestNow('2026-05-01 09:00:00');
        $earlier->delete();
        Carbon::setTestNow('2026-06-01 09:00:00');
        $w->farm->delete();
        Carbon::setTestNow();

        $this->restore($w, 'farm', $w->farm->id)->assertRedirect(route('removed.index'))->assertSessionHas('flash.banner');

        $this->assertNotSoftDeleted($w->farm);
        $this->assertNotSoftDeleted($w->field);
        $this->assertNotSoftDeleted($w->cycle);
        $this->assertNotSoftDeleted($w->planting);
        $this->assertNotSoftDeleted($w->harvest);
        $this->assertSoftDeleted($earlier);
    }

    public function test_the_flash_message_counts_what_came_back(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->delete();

        $this->restore($w, 'farm', $w->farm->id)
            ->assertSessionHas('flash.banner', fn (string $m) => str_contains($m, '1 field') && str_contains($m, '1 crop cycle') && str_contains($m, '2 records'));
    }

    public function test_a_field_cannot_come_back_while_its_farm_is_removed(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->update(['name' => 'Kopje Home Farm']);
        $w->farm->delete();

        $this->restore($w, 'field', $w->field->id)
            ->assertSessionHasErrors(['restore' => 'The field "'.$w->field->name.'" cannot be restored while its farm "Kopje Home Farm" is removed. Restore the farm first.']);

        $this->assertSoftDeleted($w->field);
        $this->assertSoftDeleted($w->farm);
        $this->assertSame(0, RecordRestoration::count());
    }

    public function test_restoring_the_parent_first_then_lets_the_children_through(): void
    {
        $w = $this->world($this->teamWithOwner());
        Carbon::setTestNow('2026-05-01 09:00:00');
        $w->farm->delete();
        Carbon::setTestNow();

        $this->restore($w, 'farm', $w->farm->id);

        $this->assertNotSoftDeleted($w->field);
    }

    public function test_a_cycle_needs_its_field_and_its_crop(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->cycle->delete();
        Carbon::setTestNow('2026-05-01 09:00:00');
        $w->field->delete();
        Carbon::setTestNow();

        $this->restore($w, 'cycle', $w->cycle->id)->assertSessionHasErrors('restore');
        $this->assertSoftDeleted($w->cycle);

        $this->restore($w, 'field', $w->field->id)->assertSessionHasNoErrors();
        $this->assertSoftDeleted($w->cycle);
        CropCycle::withoutGlobalScopes()->whereKey($w->cycle->id)->update(['crop_id' => ($other = Crop::factory()->create(['team_id' => $w->team->id]))->id]);
        $other->delete();

        $this->restore($w, 'cycle', $w->cycle->id)
            ->tap(fn ($r) => $this->assertRefused($r, 'crop', 'Restore the crop first'));
        $this->assertSoftDeleted($w->cycle);

        $this->restore($w, 'crop', $other->id)->assertSessionHasNoErrors();
        $this->restore($w, 'cycle', $w->cycle->id)->assertSessionHasNoErrors();
        $this->assertNotSoftDeleted($w->cycle);
    }

    public function test_plantings_and_harvests_need_their_cycle_live(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->planting->delete();
        $w->harvest->delete();
        Carbon::setTestNow('2026-05-01 09:00:00');
        $w->cycle->delete();
        Carbon::setTestNow();

        foreach (['planting' => $w->planting, 'harvest' => $w->harvest] as $type => $record) {
            $this->restore($w, $type, $record->id)
                ->tap(fn ($r) => $this->assertRefused($r, 'Restore the crop cycle first'));
            $this->assertSoftDeleted($record);
        }
    }

    public function test_the_topmost_removed_ancestor_is_the_one_named(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->update(['name' => 'Kopje Home Farm']);
        $w->farm->delete();

        $this->restore($w, 'harvest', $w->harvest->id)
            ->tap(fn ($r) => $this->assertRefused($r, 'farm "Kopje Home Farm"', 'Restore the farm first'));
    }

    public function test_a_field_whose_code_was_reused_by_a_live_field_is_refused_with_the_reason(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->field->update(['code' => 'N-01']);
        $w->field->delete();
        Field::factory()->create(['farm_id' => $w->farm->id, 'code' => 'N-01', 'name' => 'New North']);

        $this->restore($w, 'field', $w->field->id)
            ->tap(fn ($r) => $this->assertRefused($r, 'N-01', 'New North'));
        $this->assertSoftDeleted($w->field);
    }

    public function test_restoring_a_planting_re_derives_the_cycle_status_and_planting_date(): void
    {
        $w = $this->world($this->teamWithOwner(), 'planted');
        $w->harvest->forceDelete();
        app(CropCycleLifecycle::class)->deletePlanting($w->planting);
        $this->assertSame(CropCycleStatus::Planned, $w->cycle->fresh()->status);
        $this->assertNull($w->cycle->fresh()->planted_at);

        $this->restore($w, 'planting', $w->planting->id)->assertSessionHasNoErrors();

        $cycle = $w->cycle->fresh();
        $this->assertSame(CropCycleStatus::Planted, $cycle->status);
        $this->assertSame($w->planting->fresh()->planted_at->toDateString(), $cycle->planted_at->toDateString());
    }

    public function test_a_planting_dated_after_an_existing_harvest_is_refused(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->planting->delete();
        PlantingRecord::withoutGlobalScopes()->whereKey($w->planting->id)->update(['planted_at' => now()->addDays(3)]);

        $this->restore($w, 'planting', $w->planting->id)
            ->tap(fn ($r) => $this->assertRefused($r, 'cannot be dated after a harvest'));
        $this->assertSoftDeleted($w->planting);
    }

    public function test_restoring_a_harvest_goes_through_the_cycle_rules(): void
    {
        $w = $this->world($this->teamWithOwner());
        app(CropCycleLifecycle::class)->deleteHarvest($w->harvest);

        $this->restore($w, 'harvest', $w->harvest->id)->assertSessionHasNoErrors();
        $this->assertNotSoftDeleted($w->harvest);
        $this->assertSame(CropCycleStatus::Growing, $w->cycle->fresh()->status, 'a restored harvest must not close the cycle');
    }

    public function test_a_harvest_cannot_be_restored_into_a_failed_or_planned_cycle(): void
    {
        $worlds = ['is failed' => $this->world($this->teamWithOwner(), 'failed'), 'has not been planted' => $this->world($this->teamWithOwner(), 'planned')];

        foreach ($worlds as $phrase => $w) {
            $w->harvest->delete();

            $this->restore($w, 'harvest', $w->harvest->id)
                ->tap(fn ($r) => $this->assertRefused($r, $phrase));
            $this->assertSoftDeleted($w->harvest);
        }
    }

    public function test_a_harvest_dated_before_the_planting_is_refused(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->harvest->delete();
        HarvestRecord::withoutGlobalScopes()->whereKey($w->harvest->id)->update(['harvested_at' => now()->subYear()]);

        $this->restore($w, 'harvest', $w->harvest->id)
            ->tap(fn ($r) => $this->assertRefused($r, 'cannot be dated before the planting'));
    }

    public function test_a_restored_harvest_counts_in_totals_by_unit_family_and_legacy_units_stay_separate(): void
    {
        $w = $this->world($this->teamWithOwner());
        $tonnes = HarvestRecord::factory()->create(['crop_cycle_id' => $w->cycle->id, 'harvested_at' => now()->subDay(), 'unit' => 't', 'quantity_harvested' => 2]);
        $legacy = HarvestRecord::factory()->create(['crop_cycle_id' => $w->cycle->id, 'harvested_at' => now()->subDay(), 'unit' => 'sack', 'quantity_harvested' => 7]);
        $tonnes->delete();
        $legacy->delete();

        $before = $this->actingAs($w->team->owner)->get(route('harvests.index'))->viewData('totals');
        $this->assertEqualsWithDelta(100.0, $before->amountFor('weight'), 0.001);

        $this->restore($w, 'harvest', $tonnes->id)->assertSessionHasNoErrors();
        $this->restore($w, 'harvest', $legacy->id)->assertSessionHasNoErrors();

        $after = $this->get(route('harvests.index'))->viewData('totals');
        $this->assertEqualsWithDelta(2100.0, $after->amountFor('weight'), 0.001);
        $this->assertEqualsWithDelta(7.0, $after->amountFor('sack'), 0.001);
    }

    public function test_restoring_a_cycle_brings_its_records_and_leaves_the_status_consistent(): void
    {
        $w = $this->world($this->teamWithOwner());
        app(CropCycleLifecycle::class)->close($w->cycle);
        $w->cycle->delete();

        $this->restore($w, 'cycle', $w->cycle->id)->assertSessionHasNoErrors();

        $cycle = $w->cycle->fresh();
        $this->assertSame(CropCycleStatus::Harvested, $cycle->status);
        $this->assertNotSoftDeleted($w->harvest);
        $this->assertNotSoftDeleted($w->planting);
    }

    public function test_reconcile_repairs_a_cycle_whose_facts_no_longer_match_its_status(): void
    {
        $lifecycle = app(CropCycleLifecycle::class);

        $w = $this->world($this->teamWithOwner(), 'harvested');
        $w->harvest->delete();
        $lifecycle->reconcile($w->cycle);
        $this->assertSame(CropCycleStatus::Planted, $w->cycle->fresh()->status);
        $this->assertNull($w->cycle->fresh()->closed_at);

        $w = $this->world($this->teamWithOwner(), 'growing');
        $w->harvest->delete();
        $w->planting->delete();
        $lifecycle->reconcile($w->cycle);
        $this->assertSame(CropCycleStatus::Planned, $w->cycle->fresh()->status);
        $this->assertNull($w->cycle->fresh()->planted_at);

        $w = $this->world($this->teamWithOwner(), 'planned');
        $lifecycle->reconcile($w->cycle);
        $this->assertSame(CropCycleStatus::Planted, $w->cycle->fresh()->status);
    }

    public function test_a_cycle_restored_without_its_crop_cascade_skips_it_and_says_so(): void
    {
        $w = $this->world($this->teamWithOwner());
        $other = Crop::factory()->create(['team_id' => $w->team->id]);
        $second = CropCycle::factory()->planted()->create(['field_id' => $w->field->id, 'crop_id' => $other->id]);
        $w->field->delete();
        $other->delete();

        $this->restore($w, 'field', $w->field->id)
            ->assertSessionHas('flash.banner', fn (string $m) => str_contains($m, '1 crop cycle left removed'));

        $this->assertNotSoftDeleted($w->cycle);
        $this->assertSoftDeleted($second);
    }

    public function test_restoring_never_sends_a_harvest_notification(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->harvest->delete();
        Notification::fake();

        $this->restore($w, 'harvest', $w->harvest->id)->assertSessionHasNoErrors();

        Notification::assertNothingSent();
        Notification::assertNotSentTo($w->team->owner, HarvestRecordedNotification::class);
    }

    public function test_a_restore_writes_one_append_only_audit_row(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->update(['name' => 'Audit Trail Farm']);
        $w->farm->delete();

        $this->restore($w, 'farm', $w->farm->id);

        $this->assertDatabaseCount('record_restorations', 1);
        $row = RecordRestoration::first();
        $this->assertSame($w->team->id, $row->team_id);
        $this->assertSame($w->team->owner->id, $row->user_id);
        $this->assertSame('farm', $row->record_type);
        $this->assertSame($w->farm->id, $row->record_id);
        $this->assertSame('Audit Trail Farm', $row->record_label);
        $this->assertSame(4, $row->restored_with);

        $this->expectException(\LogicException::class);
        $row->update(['record_label' => 'rewritten']);
    }

    public function test_an_audit_row_cannot_be_deleted_through_the_model(): void
    {
        $row = RecordRestoration::create([
            'team_id' => $this->teamWithOwner()->id, 'user_id' => null, 'record_type' => 'farm',
            'record_id' => 1, 'record_label' => 'x', 'restored_with' => 0,
        ]);

        $this->expectException(\LogicException::class);
        $row->delete();
    }

    public function test_a_refused_restore_leaves_no_audit_row(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->delete();

        $this->restore($w, 'field', $w->field->id);

        $this->assertDatabaseCount('record_restorations', 0);
    }

    public function test_a_record_that_is_not_removed_or_a_type_that_does_not_exist_404s(): void
    {
        $w = $this->world($this->teamWithOwner());

        $this->restore($w, 'farm', $w->farm->id)->assertNotFound();
        $this->restore($w, 'invoice', $w->farm->id)->assertNotFound();
        $this->restore($w, 'farm', 999999)->assertNotFound();
    }

    public function test_restoring_twice_is_a_404_and_writes_one_audit_row(): void
    {
        $w = $this->world($this->teamWithOwner());
        $crop = Crop::factory()->create(['team_id' => $w->team->id]);
        $crop->delete();

        $this->restore($w, 'crop', $crop->id)->assertRedirect();
        $this->restore($w, 'crop', $crop->id)->assertNotFound();

        $this->assertDatabaseCount('record_restorations', 1);
    }

    public function test_editors_and_viewers_cannot_restore_and_nothing_changes(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->delete();

        foreach (['editor', 'viewer'] as $role) {
            $this->restore($w, 'farm', $w->farm->id, $role)->assertForbidden();
        }

        $this->assertSoftDeleted($w->farm);
        $this->assertSoftDeleted($w->field);
        $this->assertDatabaseCount('record_restorations', 0);
    }

    public function test_an_admin_can_restore(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->delete();

        $this->restore($w, 'farm', $w->farm->id, 'admin')->assertRedirect(route('removed.index'));

        $this->assertNotSoftDeleted($w->farm);
    }
}
