<?php

namespace Database\Factories;

use App\Models\CropCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlantingRecord>
 */
class PlantingRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'crop_cycle_id' => CropCycle::factory(),
            'planted_at' => $this->faker->dateTimeBetween('-4 months', '-2 months'),
            'quantity_planted' => $this->faker->randomFloat(2, 10, 500),
            'unit' => $this->faker->randomElement(['kg', 'seeds', 'seedlings']),
            'method' => $this->faker->randomElement(['direct-seed', 'transplant', 'broadcast']),
            'notes' => null,
        ];
    }
}
