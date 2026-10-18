<?php

namespace Tests\Feature\Farms;

use App\Enums\CropCycleStatus;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\PlantingRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFarmWorld;
use Tests\TestCase;

class FarmOperationsTest extends TestCase
{
    use BuildsFarmWorld;
    use RefreshDatabase;

    private function owner(string $state = 'growing'): array
    {
        $team = $this->teamWithOwner();
        $w = $this->world($team, $state);
        $this->actingAs($team->owner);

        return [$w, $this->cycleRouteParams($w)];
    }

    // ---- farms ---------------------------------------------------------

    public function test_a_farm_can_be_edited(): void
    {
        [$w] = $this->owner();

        $this->get(route('farms.edit', $w->farm))->assertOk()->assertSee($w->farm->name);
        $this->put(route('farms.update', $w->farm), ['name' => 'Renamed Farm', 'status' => 'inactive', 'size_hectares' => 10])
            ->assertRedirect(route('farms.show', $w->farm));

        $this->assertDatabaseHas('farms', ['id' => $w->farm->id, 'name' => 'Renamed Farm', 'status' => 'inactive']);
    }

    public function test_deleting_a_farm_with_fields_requires_confirmation_then_cascades_softly(): void
    {
        [$w] = $this->owner();

        $this->delete(route('farms.destroy', $w->farm))->assertSessionHasErrors('confirm_cascade');
        $this->assertNotSoftDeleted($w->farm);
        $this->assertNotSoftDeleted($w->field);

        $this->delete(route('farms.destroy', $w->farm), ['confirm_cascade' => 1])->assertRedirect(route('farms.index'));

        $this->assertSoftDeleted($w->farm);
        $this->assertSoftDeleted($w->field);
        $this->assertSoftDeleted($w->cycle);
        $this->assertSoftDeleted($w->harvest);
        $this->get(route('farms.index'))->assertOk()->assertDontSee(route('farms.show', $w->farm), false);
        $this->get(route('farms.show', $w->farm))->assertNotFound();
    }

    public function test_an_empty_farm_deletes_without_confirmation(): void
    {
        $team = $this->teamWithOwner();
        $farm = Farm::factory()->create(['team_id' => $team->id]);

        $this->actingAs($team->owner)->delete(route('farms.destroy', $farm))->assertRedirect(route('farms.index'));

        $this->assertSoftDeleted($farm);
    }

    public function test_the_farm_edit_page_explains_what_deleting_removes(): void
    {
        [$w] = $this->owner();

        $this->get(route('farms.edit', $w->farm))
            ->assertSee(route('farms.destroy', $w->farm), false)
            ->assertSee('confirm_cascade', false)
            ->assertSee('1 field');
    }

    // ---- fields --------------------------------------------------------

    public function test_a_field_can_be_edited_and_keeps_its_code_unique_among_live_fields(): void
    {
        [$w] = $this->owner();
        $other = Field::factory()->create(['farm_id' => $w->farm->id, 'code' => 'TAKEN']);

        $this->get(route('fields.edit', [$w->farm, $w->field]))->assertOk();

        $this->put(route('fields.update', [$w->farm, $w->field]), ['name' => 'N', 'code' => 'TAKEN', 'status' => 'active'])
            ->assertSessionHasErrors('code');

        $this->put(route('fields.update', [$w->farm, $w->field]), ['name' => 'North 2', 'code' => $w->field->code, 'status' => 'fallow', 'size_hectares' => 12.5])
            ->assertRedirect(route('fields.show', [$w->farm, $w->field]));
        $this->assertDatabaseHas('fields', ['id' => $w->field->id, 'name' => 'North 2', 'status' => 'fallow']);

        $other->delete();
        $this->put(route('fields.update', [$w->farm, $w->field]), ['name' => 'N', 'code' => 'TAKEN', 'status' => 'active'])
            ->assertSessionHasNoErrors();
    }

    public function test_deleting_a_field_with_cycles_needs_confirmation(): void
    {
        [$w] = $this->owner();

        $this->delete(route('fields.destroy', [$w->farm, $w->field]))->assertSessionHasErrors('confirm_cascade');
        $this->assertNotSoftDeleted($w->field);

        $this->delete(route('fields.destroy', [$w->farm, $w->field]), ['confirm_cascade' => 1])->assertRedirect(route('farms.show', $w->farm));
        $this->assertSoftDeleted($w->field);
        $this->assertSoftDeleted($w->cycle);
        $this->assertSoftDeleted($w->harvest);
    }

    // ---- crops ---------------------------------------------------------

    public function test_a_crop_can_be_edited(): void
    {
        [$w] = $this->owner();

        $this->get(route('crops.edit', $w->crop))->assertOk();
        $this->put(route('crops.update', $w->crop), ['name' => 'Sorghum', 'variety' => 'Red', 'category' => 'grain', 'typical_cycle_days' => 110])
            ->assertRedirect(route('crops.index'));

        $this->assertDatabaseHas('crops', ['id' => $w->crop->id, 'name' => 'Sorghum', 'typical_cycle_days' => 110]);
    }

    public function test_a_crop_in_use_cannot_be_deleted_and_an_unused_one_can(): void
    {
        [$w] = $this->owner();
        $unused = Crop::factory()->create(['team_id' => $w->team->id]);

        $this->delete(route('crops.destroy', $w->crop))->assertSessionHasErrors('crop');
        $this->assertNotSoftDeleted($w->crop);

        $this->delete(route('crops.destroy', $unused))->assertRedirect(route('crops.index'));
        $this->assertSoftDeleted($unused);
    }

    public function test_the_crop_list_says_why_a_used_crop_has_no_delete(): void
    {
        [$w] = $this->owner();

        $this->get(route('crops.index'))->assertOk()
            ->assertSee(route('crops.edit', $w->crop), false)
            ->assertDontSee('action="'.route('crops.destroy', $w->crop).'"', false)
            ->assertSee('in use');
    }

    public function test_a_deleted_crop_cannot_be_planned_into_a_cycle(): void
    {
        [$w] = $this->owner();
        $gone = Crop::factory()->create(['team_id' => $w->team->id]);
        $gone->delete();

        $this->post(route('crop-cycles.store', [$w->farm, $w->field]), ['crop_id' => $gone->id, 'season' => '2027'])
            ->assertSessionHasErrors('crop_id');
    }

    // ---- cycles --------------------------------------------------------

    public function test_a_cycle_can_be_edited_and_its_crop_only_changed_while_planned(): void
    {
        [$w, $params] = $this->owner('planned');
        $other = Crop::factory()->create(['team_id' => $w->team->id]);

        $this->get(route('crop-cycles.edit', $params))->assertOk();
        $this->put(route('crop-cycles.update', $params), ['season' => '2027 Winter', 'crop_id' => $other->id, 'expected_harvest_at' => '2027-09-01', 'notes' => 'moved'])
            ->assertRedirect(route('crop-cycles.show', $params));
        $this->assertDatabaseHas('crop_cycles', ['id' => $w->cycle->id, 'season' => '2027 Winter', 'crop_id' => $other->id]);

        $w->cycle->update(['status' => CropCycleStatus::Planted]);
        $this->put(route('crop-cycles.update', $params), ['season' => '2027 Winter', 'crop_id' => $w->crop->id])
            ->assertSessionHasErrors('crop_id');
        $this->assertDatabaseHas('crop_cycles', ['id' => $w->cycle->id, 'crop_id' => $other->id]);
    }

    public function test_deleting_a_cycle_with_records_needs_confirmation(): void
    {
        [$w, $params] = $this->owner();

        $this->delete(route('crop-cycles.destroy', $params))->assertSessionHasErrors('confirm_cascade');
        $this->delete(route('crop-cycles.destroy', $params), ['confirm_cascade' => 1])->assertRedirect(route('fields.show', [$w->farm, $w->field]));

        $this->assertSoftDeleted($w->cycle);
        $this->assertSoftDeleted($w->planting);
        $this->assertSoftDeleted($w->harvest);
    }

    public function test_status_actions_run_through_the_state_machine(): void
    {
        [$w, $params] = $this->owner('planted');
        $transition = route('crop-cycles.transition', $params);

        $this->post($transition, ['action' => 'growing'])->assertRedirect(route('crop-cycles.show', $params));
        $this->assertSame(CropCycleStatus::Growing, $w->cycle->refresh()->status);

        $this->post($transition, ['action' => 'close'])->assertRedirect();
        $this->assertSame(CropCycleStatus::Harvested, $w->cycle->refresh()->status);

        $this->post($transition, ['action' => 'growing'])->assertSessionHasErrors('cycle');
        $this->post($transition, ['action' => 'reopen'])->assertRedirect();
        $this->assertSame(CropCycleStatus::Growing, $w->cycle->refresh()->status);

        $this->post($transition, ['action' => 'fail'])->assertSessionHasErrors('reason');
        $this->post($transition, ['action' => 'fail', 'reason' => 'Drought'])->assertRedirect();
        $w->cycle->refresh();
        $this->assertSame(CropCycleStatus::Failed, $w->cycle->status);
        $this->assertSame('Drought', $w->cycle->end_reason);

        $this->post($transition, ['action' => 'bogus'])->assertSessionHasErrors('action');
    }

    public function test_closing_with_no_harvest_is_refused(): void
    {
        [$w, $params] = $this->owner('growing');
        $w->harvest->delete();

        $this->post(route('crop-cycles.transition', $params), ['action' => 'close'])->assertSessionHasErrors('cycle');
        $this->assertSame(CropCycleStatus::Growing, $w->cycle->refresh()->status);
    }

    public function test_the_cycle_page_offers_only_the_actions_that_are_valid_now(): void
    {
        [$w, $params] = $this->owner('planted');
        $transition = route('crop-cycles.transition', $params);

        $this->get(route('crop-cycles.show', $params))->assertOk()
            ->assertSee('value="growing"', false)
            ->assertSee('value="close"', false)
            ->assertSee('value="fail"', false)
            ->assertSee('value="abandon"', false)
            ->assertDontSee('value="reopen"', false)
            ->assertSee($transition, false);

        $w->cycle->update(['status' => CropCycleStatus::Harvested]);
        $this->get(route('crop-cycles.show', $params))
            ->assertSee('value="reopen"', false)
            ->assertDontSee('value="growing"', false)
            ->assertDontSee(route('crop-cycles.harvest.store', $params), false);
    }

    // ---- plantings -----------------------------------------------------

    public function test_recording_a_planting_plants_the_cycle_with_a_standard_unit(): void
    {
        [$w, $params] = $this->owner('planned');
        $w->harvest->delete();
        $w->planting->delete();

        $this->post(route('crop-cycles.planting.store', $params), ['planted_at' => '2026-03-05', 'quantity_planted' => 12, 'unit' => 'bag'])
            ->assertRedirect(route('crop-cycles.show', $params));

        $w->cycle->refresh();
        $this->assertSame(CropCycleStatus::Planted, $w->cycle->status);
        $this->assertDatabaseHas('planting_records', ['crop_cycle_id' => $w->cycle->id, 'unit' => 'bag']);

        $this->post(route('crop-cycles.planting.store', $params), ['planted_at' => '2026-03-06', 'unit' => 'furlongs'])->assertSessionHasErrors('unit');
        $this->post(route('crop-cycles.planting.store', $params), ['planted_at' => '2026-03-06', 'unit' => 'ha', 'quantity_planted' => 3])->assertSessionHasNoErrors();
    }

    public function test_a_planting_can_be_edited_and_deleted(): void
    {
        [$w, $params] = $this->owner('planted');
        $extra = PlantingRecord::factory()->create(['crop_cycle_id' => $w->cycle->id, 'planted_at' => now()->subMonths(2), 'unit' => 'kg']);
        $route = [...$params, $extra];

        $this->get(route('plantings.edit', $route))->assertOk();
        $this->put(route('plantings.update', $route), ['planted_at' => now()->subMonths(5)->toDateString(), 'quantity_planted' => 99, 'unit' => 't'])
            ->assertRedirect(route('crop-cycles.show', $params));
        $this->assertDatabaseHas('planting_records', ['id' => $extra->id, 'unit' => 't']);
        $this->assertSame(now()->subMonths(5)->toDateString(), $w->cycle->refresh()->planted_at->toDateString());

        $this->delete(route('plantings.destroy', $route))->assertRedirect(route('crop-cycles.show', $params));
        $this->assertSoftDeleted($extra);
        $this->assertSame(now()->subMonths(3)->toDateString(), $w->cycle->refresh()->planted_at->toDateString());
    }

    public function test_the_last_planting_cannot_be_deleted_while_harvests_exist(): void
    {
        [$w, $params] = $this->owner('growing');

        $this->delete(route('plantings.destroy', [...$params, $w->planting]))->assertSessionHasErrors('cycle');
        $this->assertNotSoftDeleted($w->planting);
    }

    // ---- harvests ------------------------------------------------------

    public function test_a_harvest_cannot_be_recorded_on_a_planned_or_closed_cycle(): void
    {
        [$w, $params] = $this->owner('planned');
        $w->harvest->delete();
        $payload = ['harvested_at' => now()->toDateString(), 'quantity_harvested' => 5, 'unit' => 'kg'];

        $this->post(route('crop-cycles.harvest.store', $params), $payload)->assertSessionHasErrors('cycle');
        $this->assertDatabaseCount('harvest_records', 1); // only the soft-deleted fixture

        $w->cycle->update(['status' => CropCycleStatus::Harvested]);
        $this->post(route('crop-cycles.harvest.store', $params), $payload)->assertSessionHasErrors('cycle');
        $this->assertSame(0, HarvestRecord::count());
    }

    public function test_partial_harvests_leave_the_cycle_open_and_use_standard_units(): void
    {
        [$w, $params] = $this->owner('growing');

        $this->post(route('crop-cycles.harvest.store', $params), ['harvested_at' => now()->toDateString(), 'quantity_harvested' => 5, 'unit' => 'crate'])
            ->assertRedirect(route('crop-cycles.show', $params));
        $this->assertSame(CropCycleStatus::Growing, $w->cycle->refresh()->status);
        $this->assertSame(2, $w->cycle->harvestRecords()->count());

        $this->post(route('crop-cycles.harvest.store', $params), ['harvested_at' => now()->toDateString(), 'quantity_harvested' => 5, 'unit' => 'ton'])
            ->assertSessionHasErrors('unit');
        $this->post(route('crop-cycles.harvest.store', $params), ['harvested_at' => now()->toDateString(), 'quantity_harvested' => 5, 'unit' => 'ha'])
            ->assertSessionHasErrors('unit');
    }

    public function test_a_mistaken_harvest_can_be_corrected_and_the_cycle_stays_consistent(): void
    {
        [$w, $params] = $this->owner('growing');
        $this->post(route('crop-cycles.transition', $params), ['action' => 'close'])->assertSessionHasNoErrors();

        $this->get(route('harvests.edit', $w->harvest))->assertOk()->assertSee('100');
        $this->put(route('harvests.update', $w->harvest), ['harvested_at' => now()->subDays(2)->toDateString(), 'quantity_harvested' => 10, 'unit' => 'kg', 'quality_grade' => 'B', 'notes' => 'typo fixed'])
            ->assertRedirect(route('crop-cycles.show', $params));

        $this->assertDatabaseHas('harvest_records', ['id' => $w->harvest->id, 'quantity_harvested' => 10, 'quality_grade' => 'B']);
        $this->assertSame(CropCycleStatus::Harvested, $w->cycle->refresh()->status);
    }

    public function test_deleting_the_only_harvest_of_a_closed_cycle_reopens_it(): void
    {
        [$w, $params] = $this->owner('growing');
        $this->post(route('crop-cycles.transition', $params), ['action' => 'close']);
        $this->assertSame(CropCycleStatus::Harvested, $w->cycle->refresh()->status);

        $this->delete(route('harvests.destroy', $w->harvest))->assertRedirect(route('crop-cycles.show', $params));

        $this->assertSoftDeleted($w->harvest);
        // The cycle had been marked growing before it was closed, so it goes back to growing.
        $this->assertSame(CropCycleStatus::Growing, $w->cycle->refresh()->status);
    }

    public function test_the_harvest_pages_link_to_edit_and_delete(): void
    {
        [$w] = $this->owner();

        $this->get(route('harvests.show', $w->harvest))->assertOk()
            ->assertSee(route('harvests.edit', $w->harvest), false)
            ->assertSee(route('harvests.destroy', $w->harvest), false);
    }

    public function test_harvest_validation_rejects_a_date_before_planting(): void
    {
        [$w, $params] = $this->owner('growing');

        $this->post(route('crop-cycles.harvest.store', $params), ['harvested_at' => now()->subYear()->toDateString(), 'quantity_harvested' => 5, 'unit' => 'kg'])
            ->assertSessionHasErrors('harvested_at');
    }

    public function test_new_cycle_starts_planned(): void
    {
        [$w] = $this->owner();

        $this->post(route('crop-cycles.store', [$w->farm, $w->field]), ['crop_id' => $w->crop->id, 'season' => '2027 Summer'])->assertRedirect();

        $this->assertSame(CropCycleStatus::Planned, CropCycle::latest('id')->first()->status);
    }
}
