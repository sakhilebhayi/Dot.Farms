<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Field;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Field>
 */
class FieldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'name' => 'Field '.$this->faker->unique()->numberBetween(1, 999),
            'code' => strtoupper($this->faker->unique()->bothify('F-##')),
            'soil_type' => $this->faker->randomElement(['loam', 'clay', 'sandy', 'silt', 'peat']),
            'moisture_zone' => $this->faker->randomElement(['dry', 'moderate', 'wet']),
            'size_hectares' => $this->faker->randomFloat(2, 1, 50),
            'status' => 'active',
        ];
    }
}
