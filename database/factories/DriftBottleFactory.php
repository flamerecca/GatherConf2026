<?php

namespace Database\Factories;

use App\Models\DriftBottle;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriftBottle>
 */
class DriftBottleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_id' => Member::factory(),
            'recipient_id' => null,
            'body' => fake()->sentence(),
        ];
    }
}
