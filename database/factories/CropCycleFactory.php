<?php

namespace Database\Factories;

use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Field;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CropCycle>
 */
class CropCycleFactory extends Factory
{
    public function definition(): array
    {
        $plantedAt = $this->faker->dateTimeBetween('-4 months', 'now');

        return [
            'field_id' => Field::factory(),
            'crop_id' => Crop::factory(),
            'season' => now()->year.' '.$this->faker->randomElement(['Summer', 'Winter', 'Spring', 'Autumn']),
            'status' => CropCycle::STATUS_GROWING,
            'planted_at' => $plantedAt,
            'expected_harvest_at' => (clone $plantedAt)->modify('+120 days'),
            'notes' => null,
        ];
    }

    public function harvested(): static
    {
        return $this->state(fn () => [
            'status' => CropCycle::STATUS_HARVESTED,
        ]);
    }
}
