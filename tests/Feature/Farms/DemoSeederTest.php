<?php

namespace Tests\Feature\Farms;

use App\Enums\CropCycleStatus;
use App\Enums\Unit;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\HarvestRecord;
use App\Models\PlantingRecord;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoFarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_seeder_creates_no_data_so_it_is_safe_in_production(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, Farm::count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, Crop::count());
    }

    public function test_the_demo_seeder_refuses_to_run_outside_local_and_testing(): void
    {
        foreach (['production', 'staging'] as $env) {
            $this->app['env'] = $env;

            try {
                app(DemoFarmSeeder::class)->run();
                $this->fail("DemoFarmSeeder ran in {$env}");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('local and testing', $e->getMessage());
            }
        }

        $this->assertSame(0, Farm::count());
        $this->assertSame(0, User::count());
    }

    public function test_the_demo_seeder_runs_locally_and_only_once(): void
    {
        $this->app['env'] = 'local';
        $this->seed(DemoFarmSeeder::class);

        $this->assertSame(2, Farm::count());
        $this->assertSame(['Riverbend Farm', 'Vaalharts Farm'], Farm::orderBy('name')->pluck('name')->all());

        $this->expectException(RuntimeException::class);
        $this->seed(DemoFarmSeeder::class);
    }

    public function test_the_demo_data_obeys_the_cycle_rules_and_uses_standard_units(): void
    {
        $this->seed(DemoFarmSeeder::class);

        foreach (CropCycle::withoutGlobalScopes()->get() as $cycle) {
            $label = "{$cycle->season} {$cycle->status->value}";
            $plantings = PlantingRecord::withoutGlobalScopes()->where('crop_cycle_id', $cycle->id)->count();
            $harvests = HarvestRecord::withoutGlobalScopes()->where('crop_cycle_id', $cycle->id)->count();

            if ($cycle->status === CropCycleStatus::Planned) {
                $this->assertSame(0, $plantings + $harvests, $label);
                $this->assertNull($cycle->planted_at, $label);

                continue;
            }

            $this->assertGreaterThan(0, $plantings, "$label needs a planting");
            $this->assertNotNull($cycle->planted_at, $label);

            if ($cycle->status === CropCycleStatus::Harvested) {
                $this->assertGreaterThan(0, $harvests, "$label is closed as harvested with nothing harvested");
            }
            if ($cycle->status->isClosed()) {
                $this->assertNotNull($cycle->closed_at, $label);
            }
            if (in_array($cycle->status, [CropCycleStatus::Failed, CropCycleStatus::Abandoned], true)) {
                $this->assertNotEmpty($cycle->end_reason, $label);
            }
        }

        foreach (HarvestRecord::withoutGlobalScopes()->pluck('unit') as $unit) {
            $this->assertContains($unit, Unit::harvestValues());
        }
        foreach (PlantingRecord::withoutGlobalScopes()->pluck('unit') as $unit) {
            $this->assertContains($unit, Unit::plantingValues());
        }
    }
}
