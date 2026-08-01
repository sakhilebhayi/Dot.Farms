<?php

namespace Database\Seeders;

use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\PlantingRecord;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with a realistic Dot.Farms MVP
     * demo: one team, two farms, a handful of fields each, a small crop
     * catalog, and crop cycles at different lifecycle stages (planned,
     * planted/growing, harvested) so the dashboard has something to show.
     */
    public function run(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $team = $user->currentTeam;

        $maize = Crop::create([
            'team_id' => $team->id,
            'name' => 'Maize',
            'variety' => 'Yellow dent',
            'category' => 'grain',
            'typical_cycle_days' => 120,
        ]);

        $wheat = Crop::create([
            'team_id' => $team->id,
            'name' => 'Wheat',
            'variety' => 'Durum',
            'category' => 'grain',
            'typical_cycle_days' => 100,
        ]);

        $tomato = Crop::create([
            'team_id' => $team->id,
            'name' => 'Tomato',
            'variety' => 'Roma',
            'category' => 'vegetable',
            'typical_cycle_days' => 80,
        ]);

        $sunflower = Crop::create([
            'team_id' => $team->id,
            'name' => 'Sunflower',
            'variety' => null,
            'category' => 'grain',
            'typical_cycle_days' => 110,
        ]);

        // Farm 1: Vaalharts cluster — the farm cluster referenced in
        // Dot.Brain's worked round-trip example (platforms/dot-farms.md §13).
        $vaalharts = Farm::create([
            'team_id' => $team->id,
            'name' => 'Vaalharts Farm',
            'location' => 'Vaalharts, Northern Cape, South Africa',
            'size_hectares' => 340.50,
            'status' => 'active',
            'notes' => 'Primary irrigation-scheme farm; wet-season logistics recommendation P-2026-001 applies here.',
        ]);

        $fieldA = Field::create([
            'farm_id' => $vaalharts->id,
            'name' => 'North Paddock',
            'code' => 'VH-N1',
            'soil_type' => 'loam',
            'moisture_zone' => 'wet',
            'size_hectares' => 45.00,
            'status' => 'active',
        ]);

        $fieldB = Field::create([
            'farm_id' => $vaalharts->id,
            'name' => 'South Paddock',
            'code' => 'VH-S1',
            'soil_type' => 'clay',
            'moisture_zone' => 'moderate',
            'size_hectares' => 38.20,
            'status' => 'active',
        ]);

        $fieldC = Field::create([
            'farm_id' => $vaalharts->id,
            'name' => 'East Block',
            'code' => 'VH-E1',
            'soil_type' => 'sandy',
            'moisture_zone' => 'dry',
            'size_hectares' => 22.00,
            'status' => 'fallow',
        ]);

        // Farm 2: smaller mixed-crop operation.
        $riverbend = Farm::create([
            'team_id' => $team->id,
            'name' => 'Riverbend Farm',
            'location' => 'Riverbend, Western Cape, South Africa',
            'size_hectares' => 85.00,
            'status' => 'active',
        ]);

        $fieldD = Field::create([
            'farm_id' => $riverbend->id,
            'name' => 'Glasshouse Block',
            'code' => 'RB-G1',
            'soil_type' => 'peat',
            'moisture_zone' => 'moderate',
            'size_hectares' => 6.50,
            'status' => 'active',
        ]);

        // Growing cycle — currently in season, no harvest yet.
        $growingCycle = CropCycle::create([
            'field_id' => $fieldA->id,
            'crop_id' => $maize->id,
            'season' => '2026 Summer',
            'status' => CropCycle::STATUS_GROWING,
            'planted_at' => now()->subDays(60),
            'expected_harvest_at' => now()->addDays(60),
            'notes' => 'On track; moisture readings within band per wet-season pattern.',
        ]);

        PlantingRecord::create([
            'crop_cycle_id' => $growingCycle->id,
            'planted_at' => now()->subDays(60),
            'quantity_planted' => 1800,
            'unit' => 'kg',
            'method' => 'direct-seed',
        ]);

        // Second growing cycle on a different field/crop.
        $growingCycle2 = CropCycle::create([
            'field_id' => $fieldB->id,
            'crop_id' => $sunflower->id,
            'season' => '2026 Summer',
            'status' => CropCycle::STATUS_PLANTED,
            'planted_at' => now()->subDays(20),
            'expected_harvest_at' => now()->addDays(90),
        ]);

        PlantingRecord::create([
            'crop_cycle_id' => $growingCycle2->id,
            'planted_at' => now()->subDays(20),
            'quantity_planted' => 60,
            'unit' => 'kg',
            'method' => 'direct-seed',
        ]);

        // Harvested cycle — completed this season, feeds "recent harvests".
        $harvestedCycle = CropCycle::create([
            'field_id' => $fieldD->id,
            'crop_id' => $tomato->id,
            'season' => '2026 Summer',
            'status' => CropCycle::STATUS_HARVESTED,
            'planted_at' => now()->subDays(85),
            'expected_harvest_at' => now()->subDays(5),
        ]);

        PlantingRecord::create([
            'crop_cycle_id' => $harvestedCycle->id,
            'planted_at' => now()->subDays(85),
            'quantity_planted' => 4000,
            'unit' => 'seedlings',
            'method' => 'transplant',
        ]);

        HarvestRecord::create([
            'crop_cycle_id' => $harvestedCycle->id,
            'harvested_at' => now()->subDays(4),
            'quantity_harvested' => 12500.00,
            'unit' => 'kg',
            'quality_grade' => 'A',
            'notes' => 'Strong yield; ready for Dot.Emall listing handoff.',
        ]);

        // A second, earlier harvest on Vaalharts to show harvest history.
        $wheatCycle = CropCycle::create([
            'field_id' => $fieldC->id,
            'crop_id' => $wheat->id,
            'season' => '2026 Winter',
            'status' => CropCycle::STATUS_HARVESTED,
            'planted_at' => now()->subDays(150),
            'expected_harvest_at' => now()->subDays(30),
        ]);

        HarvestRecord::create([
            'crop_cycle_id' => $wheatCycle->id,
            'harvested_at' => now()->subDays(28),
            'quantity_harvested' => 8300.00,
            'unit' => 'kg',
            'quality_grade' => 'B',
            'notes' => 'Slightly below forecast; frost-window miss noted (incident pack 2026-01).',
        ]);

        // A planned-but-not-yet-planted cycle to show the "planned" state.
        CropCycle::create([
            'field_id' => $fieldC->id,
            'crop_id' => $maize->id,
            'season' => '2027 Summer',
            'status' => CropCycle::STATUS_PLANNED,
            'expected_harvest_at' => now()->addMonths(8),
        ]);
    }
}
