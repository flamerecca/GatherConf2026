<?php

namespace Database\Factories;

use App\Models\AuthorizationCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuthorizationCode>
 */
class AuthorizationCodeFactory extends Factory
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
            'reward' => 5,
            'max_uses' => null,
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
        return $this->state(fn (array $attributes) => ['ends_at' => now()->subDay()]);
    }

    public function limitedTo(int $uses): static
    {
        return $this->state(fn (array $attributes) => ['max_uses' => $uses]);
    }
}
