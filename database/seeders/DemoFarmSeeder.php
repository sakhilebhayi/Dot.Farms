<?php

namespace Database\Seeders;

use App\Enums\CropCycleStatus;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\PlantingRecord;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * A demo team with two farms, a small crop catalog and crop cycles at every
 * stage of the lifecycle, so the dashboard, reports and CSV export have
 * something to show on a developer machine.
 *
 * Fake data (a "Vaalharts Farm" that does not exist, a test@example.com user
 * with the factory's well-known password) must never reach a real database,
 * so this seeder refuses to run anywhere but the local and testing
 * environments and is not called by DatabaseSeeder:
 *
 *     php artisan db:seed --class=DemoFarmSeeder
 *
 * The records respect the cycle rules (a harvested cycle has a planting, a
 * harvest and a closing date; a growing cycle has a growing date), so the
 * demo is a valid starting point rather than something the state machine
 * would have refused.
 */
class DemoFarmSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('DemoFarmSeeder creates fake demo data and only runs in the local and testing environments (APP_ENV is "'.app()->environment().'").');
        }

        if (User::where('email', 'test@example.com')->exists()) {
            throw new RuntimeException('The demo user test@example.com already exists; the demo has been loaded before.');
        }

        $user = User::factory()->withPersonalTeam()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $team = $user->currentTeam;

        $maize = Crop::create(['team_id' => $team->id, 'name' => 'Maize', 'variety' => 'Yellow dent', 'category' => 'grain', 'typical_cycle_days' => 120]);
        $wheat = Crop::create(['team_id' => $team->id, 'name' => 'Wheat', 'variety' => 'Durum', 'category' => 'grain', 'typical_cycle_days' => 100]);
        $tomato = Crop::create(['team_id' => $team->id, 'name' => 'Tomato', 'variety' => 'Roma', 'category' => 'vegetable', 'typical_cycle_days' => 80]);
        $sunflower = Crop::create(['team_id' => $team->id, 'name' => 'Sunflower', 'variety' => null, 'category' => 'grain', 'typical_cycle_days' => 110]);

        $vaalharts = Farm::create([
            'team_id' => $team->id,
            'name' => 'Vaalharts Farm',
            'location' => 'Vaalharts, Northern Cape, South Africa',
            'size_hectares' => 340.50,
            'status' => 'active',
            'notes' => 'Primary irrigation-scheme farm.',
        ]);

        $fieldA = Field::create(['farm_id' => $vaalharts->id, 'name' => 'North Paddock', 'code' => 'VH-N1', 'soil_type' => 'loam', 'moisture_zone' => 'wet', 'size_hectares' => 45.00, 'status' => 'active']);
        $fieldB = Field::create(['farm_id' => $vaalharts->id, 'name' => 'South Paddock', 'code' => 'VH-S1', 'soil_type' => 'clay', 'moisture_zone' => 'moderate', 'size_hectares' => 38.20, 'status' => 'active']);
        $fieldC = Field::create(['farm_id' => $vaalharts->id, 'name' => 'East Block', 'code' => 'VH-E1', 'soil_type' => 'sandy', 'moisture_zone' => 'dry', 'size_hectares' => 22.00, 'status' => 'fallow']);

        $riverbend = Farm::create([
            'team_id' => $team->id,
            'name' => 'Riverbend Farm',
            'location' => 'Riverbend, Western Cape, South Africa',
            'size_hectares' => 85.00,
            'status' => 'active',
        ]);

        $fieldD = Field::create(['farm_id' => $riverbend->id, 'name' => 'Glasshouse Block', 'code' => 'RB-G1', 'soil_type' => 'peat', 'moisture_zone' => 'moderate', 'size_hectares' => 6.50, 'status' => 'active']);

        // Growing, partly harvested, past its expected date: shows as overdue.
        $growing = CropCycle::create([
            'field_id' => $fieldA->id, 'crop_id' => $maize->id, 'season' => '2026 Summer',
            'status' => CropCycleStatus::Growing, 'planted_at' => now()->subDays(60), 'growing_at' => now()->subDays(40),
            'expected_harvest_at' => now()->subDays(2),
            'notes' => 'First picking taken early.',
        ]);
        PlantingRecord::create(['crop_cycle_id' => $growing->id, 'planted_at' => now()->subDays(60), 'quantity_planted' => 40, 'unit' => 'ha', 'method' => 'direct-seed']);
        PlantingRecord::create(['crop_cycle_id' => $growing->id, 'planted_at' => now()->subDays(60), 'quantity_planted' => 1800, 'unit' => 'kg', 'method' => 'direct-seed', 'notes' => 'Seed supplied']);
        HarvestRecord::create(['crop_cycle_id' => $growing->id, 'harvested_at' => now()->subDays(3), 'quantity_harvested' => 18.5, 'unit' => 't', 'quality_grade' => 'A']);

        // Planted, on track.
        $planted = CropCycle::create([
            'field_id' => $fieldB->id, 'crop_id' => $sunflower->id, 'season' => '2026 Summer',
            'status' => CropCycleStatus::Planted, 'planted_at' => now()->subDays(20), 'expected_harvest_at' => now()->addDays(90),
        ]);
        PlantingRecord::create(['crop_cycle_id' => $planted->id, 'planted_at' => now()->subDays(20), 'quantity_planted' => 60, 'unit' => 'kg', 'method' => 'direct-seed']);

        // Closed as harvested, in crates.
        $tomatoes = CropCycle::create([
            'field_id' => $fieldD->id, 'crop_id' => $tomato->id, 'season' => '2026 Summer',
            'status' => CropCycleStatus::Harvested, 'planted_at' => now()->subDays(85), 'growing_at' => now()->subDays(60),
            'expected_harvest_at' => now()->subDays(5), 'closed_at' => now()->subDays(3),
        ]);
        PlantingRecord::create(['crop_cycle_id' => $tomatoes->id, 'planted_at' => now()->subDays(85), 'quantity_planted' => 4000, 'unit' => 'seedling', 'method' => 'transplant']);
        HarvestRecord::create(['crop_cycle_id' => $tomatoes->id, 'harvested_at' => now()->subDays(8), 'quantity_harvested' => 420, 'unit' => 'crate', 'quality_grade' => 'A']);
        HarvestRecord::create(['crop_cycle_id' => $tomatoes->id, 'harvested_at' => now()->subDays(4), 'quantity_harvested' => 380, 'unit' => 'crate', 'quality_grade' => 'B']);

        // An earlier season, closed, in kilograms.
        $wheatCycle = CropCycle::create([
            'field_id' => $fieldC->id, 'crop_id' => $wheat->id, 'season' => '2026 Winter',
            'status' => CropCycleStatus::Harvested, 'planted_at' => now()->subDays(150), 'growing_at' => now()->subDays(120),
            'expected_harvest_at' => now()->subDays(30), 'closed_at' => now()->subDays(27),
        ]);
        PlantingRecord::create(['crop_cycle_id' => $wheatCycle->id, 'planted_at' => now()->subDays(150), 'quantity_planted' => 22, 'unit' => 'ha', 'method' => 'broadcast']);
        HarvestRecord::create(['crop_cycle_id' => $wheatCycle->id, 'harvested_at' => now()->subDays(28), 'quantity_harvested' => 8300, 'unit' => 'kg', 'quality_grade' => 'B', 'notes' => 'Slightly below forecast; frost window missed.']);

        // A lost crop, with its reason.
        $failed = CropCycle::create([
            'field_id' => $fieldB->id, 'crop_id' => $wheat->id, 'season' => '2026 Winter',
            'status' => CropCycleStatus::Failed, 'planted_at' => now()->subDays(140), 'expected_harvest_at' => now()->subDays(40),
            'closed_at' => now()->subDays(70), 'end_reason' => 'Hail on the south block after emergence.',
        ]);
        PlantingRecord::create(['crop_cycle_id' => $failed->id, 'planted_at' => now()->subDays(140), 'quantity_planted' => 38, 'unit' => 'ha', 'method' => 'broadcast']);

        // Planned but not planted.
        CropCycle::create([
            'field_id' => $fieldC->id, 'crop_id' => $maize->id, 'season' => '2027 Summer',
            'status' => CropCycleStatus::Planned, 'expected_harvest_at' => now()->addMonths(8),
        ]);
    }
}
