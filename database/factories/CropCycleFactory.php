<?php

namespace Database\Factories;

use App\Enums\CropCycleStatus;
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
            'status' => CropCycleStatus::Growing,
            'growing_at' => null,
            'planted_at' => $plantedAt,
            'expected_harvest_at' => (clone $plantedAt)->modify('+120 days'),
            'notes' => null,
        ];
    }

    public function planned(): static
    {
        return $this->state(fn () => ['status' => CropCycleStatus::Planned, 'planted_at' => null, 'growing_at' => null]);
    }

    public function planted(): static
    {
        return $this->state(fn () => ['status' => CropCycleStatus::Planted]);
    }

    public function growing(): static
    {
        return $this->state(fn () => ['status' => CropCycleStatus::Growing, 'growing_at' => now()->subDays(10)]);
    }

    public function harvested(): static
    {
        return $this->state(fn () => ['status' => CropCycleStatus::Harvested, 'closed_at' => now()]);
    }

    public function failed(string $reason = 'Crop failed'): static
    {
        return $this->state(fn () => ['status' => CropCycleStatus::Failed, 'closed_at' => now(), 'end_reason' => $reason]);
    }

    public function abandoned(string $reason = 'Cycle abandoned'): static
    {
        return $this->state(fn () => ['status' => CropCycleStatus::Abandoned, 'closed_at' => now(), 'end_reason' => $reason]);
    }
}
