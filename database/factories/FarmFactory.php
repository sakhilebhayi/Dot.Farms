<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Farm>
 */
class FarmFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => $this->faker->lastName().' Farm',
            'location' => $this->faker->city().', South Africa',
            'size_hectares' => $this->faker->randomFloat(2, 5, 500),
            'status' => 'active',
            'notes' => null,
        ];
    }
}
