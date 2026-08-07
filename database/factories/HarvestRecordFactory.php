<?php

namespace Database\Factories;

use App\Models\CropCycle;
use App\Models\HarvestRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarvestRecord>
 */
class HarvestRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'crop_cycle_id' => CropCycle::factory(),
            'harvested_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'quantity_harvested' => $this->faker->randomFloat(2, 100, 5000),
            'unit' => $this->faker->randomElement(['kg', 'ton', 'crates']),
            'quality_grade' => $this->faker->randomElement(['A', 'B', 'C']),
            'notes' => null,
        ];
    }
}
