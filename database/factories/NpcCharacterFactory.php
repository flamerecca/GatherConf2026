<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\NpcCharacter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NpcCharacter>
 */
class NpcCharacterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->name(),
            'member_id' => Member::factory(),
        ];
    }
}
