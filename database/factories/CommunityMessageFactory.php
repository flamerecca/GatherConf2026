<?php

namespace Database\Factories;

use App\Models\CommunityMessage;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityMessage>
 */
class CommunityMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'community_slug' => 'gdg-taoyuan',
            'community_name' => 'GDG 桃園',
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
        ];
    }
}
