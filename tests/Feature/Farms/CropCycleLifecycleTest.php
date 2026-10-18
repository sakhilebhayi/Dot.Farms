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
use App\Services\Farms\CropCycleLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CropCycleLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function cycle(string $state = 'planned'): CropCycle
    {
        $team = Team::factory()->create();
        $farm = Farm::factory()->create(['team_id' => $team->id]);
        $field = Field::factory()->create(['farm_id' => $farm->id]);
        $crop = Crop::factory()->create(['team_id' => $team->id]);

        return CropCycle::factory()->{$state}()->create(['field_id' => $field->id, 'crop_id' => $crop->id]);
    }

    private function lifecycle(): CropCycleLifecycle
    {
        return app(CropCycleLifecycle::class);
    }

    private function planting(string $date = '2026-03-01', string $unit = 'kg'): array
    {
        return ['planted_at' => $date, 'quantity_planted' => 10, 'unit' => $unit];
    }

    private function harvest(string $date = '2026-06-01', float $qty = 100, string $unit = 'kg'): array
    {
        return ['harvested_at' => $date, 'quantity_harvested' => $qty, 'unit' => $unit];
    }

    public function test_planting_moves_a_planned_cycle_to_planted_and_stamps_the_first_planting_date(): void
    {
        $cycle = $this->cycle('planned');

        $this->lifecycle()->plant($cycle, $this->planting('2026-03-05'));

        $cycle->refresh();
        $this->assertSame(CropCycleStatus::Planted, $cycle->status);
        $this->assertSame('2026-03-05', $cycle->planted_at->toDateString());
        $this->assertSame(1, $cycle->plantingRecords()->count());
    }

    public function test_a_second_planting_keeps_the_status_and_the_earliest_planting_date(): void
    {
        $cycle = $this->cycle('planned');
        $this->lifecycle()->plant($cycle, $this->planting('2026-03-05'));
        $this->lifecycle()->plant($cycle->refresh(), $this->planting('2026-03-01'));

        $cycle->refresh();
        $this->assertSame(CropCycleStatus::Planted, $cycle->status);
        $this->assertSame('2026-03-01', $cycle->planted_at->toDateString());
    }

    public function test_planting_is_rejected_on_a_closed_cycle(): void
    {
        $cycle = $this->cycle('harvested');

        $this->expectException(ValidationException::class);
        try {
            $this->lifecycle()->plant($cycle, $this->planting());
        } finally {
            $this->assertSame(0, $cycle->plantingRecords()->count());
        }
    }

    public function test_a_harvest_cannot_be_recorded_on_a_planned_cycle(): void
    {
        $cycle = $this->cycle('planned');

        try {
            $this->lifecycle()->recordHarvest($cycle, $this->harvest());
            $this->fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cycle', $e->errors());
        }

        $this->assertSame(0, HarvestRecord::count());
        $this->assertSame(CropCycleStatus::Planned, $cycle->refresh()->status);
    }

    public function test_a_harvest_cannot_be_recorded_on_an_already_harvested_cycle(): void
    {
        $cycle = $this->cycle('harvested');

        $this->expectException(ValidationException::class);
        $this->lifecycle()->recordHarvest($cycle, $this->harvest());
    }

    public function test_partial_harvests_leave_the_cycle_open(): void
    {
        $cycle = $this->cycle('growing');

        $this->lifecycle()->recordHarvest($cycle, $this->harvest('2026-06-01', 100));
        $this->lifecycle()->recordHarvest($cycle->refresh(), $this->harvest('2026-06-08', 150));

        $cycle->refresh();
        $this->assertSame(CropCycleStatus::Growing, $cycle->status);
        $this->assertSame(2, $cycle->harvestRecords()->count());
    }

    public function test_a_harvest_cannot_predate_the_planting(): void
    {
        $cycle = $this->cycle('planned');
        $this->lifecycle()->plant($cycle, $this->planting('2026-03-05'));

        try {
            $this->lifecycle()->recordHarvest($cycle->refresh(), $this->harvest('2026-03-04'));
            $this->fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('harvested_at', $e->errors());
        }
    }

    public function test_mark_growing_only_from_planted(): void
    {
        $planted = $this->cycle('planted');
        $this->lifecycle()->markGrowing($planted);
        $this->assertSame(CropCycleStatus::Growing, $planted->refresh()->status);
        $this->assertNotNull($planted->growing_at);

        foreach (['planned', 'growing', 'harvested', 'failed', 'abandoned'] as $state) {
            try {
                $this->lifecycle()->markGrowing($this->cycle($state));
                $this->fail("a {$state} cycle was marked growing");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('cycle', $e->errors(), $state);
            }
        }
    }

    public function test_closing_requires_at_least_one_harvest(): void
    {
        $cycle = $this->cycle('growing');

        try {
            $this->lifecycle()->close($cycle);
            $this->fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cycle', $e->errors());
        }

        $this->lifecycle()->recordHarvest($cycle->refresh(), $this->harvest());
        $this->lifecycle()->close($cycle->refresh());

        $cycle->refresh();
        $this->assertSame(CropCycleStatus::Harvested, $cycle->status);
        $this->assertNotNull($cycle->closed_at);
    }

    public function test_failing_and_abandoning_need_a_reason(): void
    {
        $cycle = $this->cycle('growing');

        foreach (['fail', 'abandon'] as $method) {
            try {
                $this->lifecycle()->{$method}($cycle->refresh(), '   ');
                $this->fail("{$method} accepted an empty reason");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('reason', $e->errors());
            }
        }

        $this->lifecycle()->fail($cycle->refresh(), 'Hail on 14 Feb');
        $cycle->refresh();
        $this->assertSame(CropCycleStatus::Failed, $cycle->status);
        $this->assertSame('Hail on 14 Feb', $cycle->end_reason);
        $this->assertNotNull($cycle->closed_at);

        $planned = $this->cycle('planned');
        $this->lifecycle()->abandon($planned, 'Field rested instead');
        $this->assertSame(CropCycleStatus::Abandoned, $planned->refresh()->status);
    }

    public function test_a_planned_cycle_cannot_be_marked_failed(): void
    {
        $this->expectException(ValidationException::class);
        $this->lifecycle()->fail($this->cycle('planned'), 'No reason');
    }

    public function test_a_closed_cycle_cannot_be_failed_or_abandoned(): void
    {
        $this->expectException(ValidationException::class);
        $this->lifecycle()->abandon($this->cycle('harvested'), 'Too late');
    }

    public function test_reopen_returns_to_the_state_the_facts_support(): void
    {
        $cycle = $this->cycle('planned');
        $this->lifecycle()->plant($cycle, $this->planting('2026-03-01'));
        $this->lifecycle()->markGrowing($cycle->refresh());
        $this->lifecycle()->recordHarvest($cycle->refresh(), $this->harvest());
        $this->lifecycle()->close($cycle->refresh());

        $this->lifecycle()->reopen($cycle->refresh());
        $cycle->refresh();

        $this->assertSame(CropCycleStatus::Growing, $cycle->status);
        $this->assertNull($cycle->closed_at);
        $this->assertNull($cycle->end_reason);

        $abandonedBeforePlanting = $this->cycle('planned');
        $this->lifecycle()->abandon($abandonedBeforePlanting, 'Changed plan');
        $this->lifecycle()->reopen($abandonedBeforePlanting->refresh());
        $this->assertSame(CropCycleStatus::Planned, $abandonedBeforePlanting->refresh()->status);
    }

    public function test_reopening_an_open_cycle_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->lifecycle()->reopen($this->cycle('growing'));
    }

    public function test_editing_a_planting_recomputes_the_cycle_planting_date(): void
    {
        $cycle = $this->cycle('planned');
        $record = $this->lifecycle()->plant($cycle, $this->planting('2026-03-05'));

        $this->lifecycle()->updatePlanting($record, $this->planting('2026-02-20'));

        $this->assertSame('2026-02-20', $cycle->refresh()->planted_at->toDateString());
    }

    public function test_editing_a_planting_later_than_an_existing_harvest_is_rejected(): void
    {
        $cycle = $this->cycle('planned');
        $planting = $this->lifecycle()->plant($cycle, $this->planting('2026-03-05'));
        $this->lifecycle()->recordHarvest($cycle->refresh(), $this->harvest('2026-06-01'));

        $this->expectException(ValidationException::class);
        $this->lifecycle()->updatePlanting($planting, $this->planting('2026-07-01'));
    }

    public function test_deleting_the_only_planting_of_a_planted_cycle_sends_it_back_to_planned(): void
    {
        $cycle = $this->cycle('planned');
        $record = $this->lifecycle()->plant($cycle, $this->planting());

        $this->lifecycle()->deletePlanting($record);

        $cycle->refresh();
        $this->assertSame(CropCycleStatus::Planned, $cycle->status);
        $this->assertNull($cycle->planted_at);
        $this->assertSoftDeleted($record);
    }

    public function test_deleting_the_last_planting_is_rejected_while_harvests_exist(): void
    {
        $cycle = $this->cycle('planned');
        $record = $this->lifecycle()->plant($cycle, $this->planting('2026-03-01'));
        $this->lifecycle()->recordHarvest($cycle->refresh(), $this->harvest());

        try {
            $this->lifecycle()->deletePlanting($record);
            $this->fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cycle', $e->errors());
        }
        $this->assertNotSoftDeleted($record);
    }

    public function test_deleting_the_only_harvest_of_a_closed_cycle_reopens_it(): void
    {
        $cycle = $this->cycle('planned');
        $this->lifecycle()->plant($cycle, $this->planting('2026-03-01'));
        $harvest = $this->lifecycle()->recordHarvest($cycle->refresh(), $this->harvest());
        $this->lifecycle()->close($cycle->refresh());
        $this->assertSame(CropCycleStatus::Harvested, $cycle->refresh()->status);

        $this->lifecycle()->deleteHarvest($harvest);

        $cycle->refresh();
        $this->assertSame(CropCycleStatus::Planted, $cycle->status);
        $this->assertNull($cycle->closed_at);
        $this->assertSoftDeleted($harvest);
    }

    public function test_deleting_one_of_several_harvests_leaves_a_closed_cycle_closed(): void
    {
        $cycle = $this->cycle('growing');
        $a = $this->lifecycle()->recordHarvest($cycle, $this->harvest('2026-06-01', 100));
        $this->lifecycle()->recordHarvest($cycle->refresh(), $this->harvest('2026-06-02', 50));
        $this->lifecycle()->close($cycle->refresh());

        $this->lifecycle()->deleteHarvest($a);

        $this->assertSame(CropCycleStatus::Harvested, $cycle->refresh()->status);
    }

    public function test_a_harvest_can_be_corrected_in_place_on_a_closed_cycle(): void
    {
        $cycle = $this->cycle('growing');
        $harvest = $this->lifecycle()->recordHarvest($cycle, $this->harvest('2026-06-01', 1000));
        $this->lifecycle()->close($cycle->refresh());

        $this->lifecycle()->updateHarvest($harvest, $this->harvest('2026-06-01', 100, 'kg'));

        $this->assertSame('100.00', $harvest->refresh()->quantity_harvested);
        $this->assertSame(CropCycleStatus::Harvested, $cycle->refresh()->status);
    }

    public function test_a_failed_record_leaves_no_partial_state(): void
    {
        $cycle = $this->cycle('planned');

        try {
            $this->lifecycle()->recordHarvest($cycle, $this->harvest());
        } catch (ValidationException) {
        }

        $this->assertSame(0, HarvestRecord::count());
    }

    public function test_the_record_and_the_status_change_commit_or_roll_back_together(): void
    {
        $cycle = $this->cycle('planned');

        // Let the planting record insert succeed, then make the cycle update blow up.
        CropCycle::updating(function (): void {
            throw new \RuntimeException('boom');
        });

        try {
            $this->lifecycle()->plant($cycle, $this->planting());
            $this->fail('Expected the forced failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame(0, PlantingRecord::count(), 'the planting record must roll back with the status change');
        $this->assertSame(CropCycleStatus::Planned, $cycle->refresh()->status);
    }
}
