<?php

namespace Database\Factories;

use App\Models\CarrotTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarrotTask>
 */
class CarrotTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'reward' => 3,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function notStarted(): static
    {
        return $this->state(fn (array $attributes) => ['starts_at' => now()->addDay()]);
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => ['ends_at' => now()->subMinute()]);
    }
}
