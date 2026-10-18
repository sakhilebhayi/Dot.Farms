<?php

namespace Tests\Feature\Farms;

use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\PlantingRecord;
use App\Models\Team;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeleteCascadeTest extends TestCase
{
    use RefreshDatabase;

    private function chain(): array
    {
        $team = Team::factory()->create();
        $farm = Farm::factory()->create(['team_id' => $team->id]);
        $field = Field::factory()->create(['farm_id' => $farm->id]);
        $crop = Crop::factory()->create(['team_id' => $team->id]);
        $cycle = CropCycle::factory()->create(['field_id' => $field->id, 'crop_id' => $crop->id]);

        return [
            $farm, $field, $crop, $cycle,
            PlantingRecord::factory()->create(['crop_cycle_id' => $cycle->id]),
            HarvestRecord::factory()->create(['crop_cycle_id' => $cycle->id]),
        ];
    }

    public function test_deleting_a_farm_softly_cascades_to_everything_beneath_it(): void
    {
        [$farm, $field, $crop, $cycle, $planting, $harvest] = $this->chain();

        $farm->delete();

        $this->assertSoftDeleted($farm);
        $this->assertSoftDeleted($field);
        $this->assertSoftDeleted($cycle);
        $this->assertSoftDeleted($planting);
        $this->assertSoftDeleted($harvest);
        $this->assertNotSoftDeleted($crop);
        $this->assertDatabaseCount('harvest_records', 1);
    }

    public function test_deleting_a_field_cascades_to_cycles_and_their_records(): void
    {
        [$farm, $field, , $cycle, $planting, $harvest] = $this->chain();

        $field->delete();

        $this->assertNotSoftDeleted($farm);
        $this->assertSoftDeleted($cycle);
        $this->assertSoftDeleted($planting);
        $this->assertSoftDeleted($harvest);
    }

    public function test_a_crop_used_by_a_cycle_cannot_be_deleted_or_force_deleted(): void
    {
        [, , $crop] = $this->chain();

        $this->assertTrue($crop->isInUse());

        foreach (['delete', 'forceDelete'] as $method) {
            try {
                $crop->{$method}();
                $this->fail("{$method} succeeded on a crop that is in use");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('in use', $e->getMessage());
            }
        }

        $this->assertDatabaseHas('crops', ['id' => $crop->id, 'deleted_at' => null]);
        $this->assertDatabaseCount('crop_cycles', 1);
    }

    public function test_an_unused_crop_can_be_soft_deleted_but_not_force_deleted_once_cycles_ever_used_it(): void
    {
        [, , $crop, $cycle] = $this->chain();

        $cycle->delete();

        $this->assertFalse($crop->fresh()->isInUse());
        $crop->delete();
        $this->assertSoftDeleted($crop);

        $this->expectException(\RuntimeException::class);
        Crop::withTrashed()->find($crop->id)->forceDelete();
    }

    public function test_a_deleted_crop_still_names_its_historical_cycles(): void
    {
        [, , $crop, $cycle] = $this->chain();
        $cycle->delete();
        $crop->delete();

        $this->assertSame($crop->name, CropCycle::withTrashed()->find($cycle->id)->crop->name);
    }

    public function test_a_field_code_can_be_reused_after_the_field_is_deleted(): void
    {
        [$farm, $field] = $this->chain();
        $code = $field->code;

        $field->delete();

        $this->assertNotNull(Field::create(['farm_id' => $farm->id, 'name' => 'Replacement', 'code' => $code, 'status' => 'active']));
    }

    public function test_two_live_fields_on_one_farm_still_cannot_share_a_code(): void
    {
        [$farm, $field] = $this->chain();

        $this->expectException(QueryException::class);
        Field::create(['farm_id' => $farm->id, 'name' => 'Clash', 'code' => $field->code, 'status' => 'active']);
    }
}
