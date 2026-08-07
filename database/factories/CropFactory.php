<?php

namespace Database\Factories;

use App\Models\Crop;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Crop>
 */
class CropFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => $this->faker->randomElement(['Maize', 'Wheat', 'Soybean', 'Tomato', 'Sunflower', 'Potato']),
            'variety' => $this->faker->optional()->word(),
            'category' => $this->faker->randomElement(['grain', 'vegetable', 'legume', 'fruit']),
            'typical_cycle_days' => $this->faker->numberBetween(60, 180),
        ];
    }
}
