<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberBlock>
 */
class MemberBlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blocker_id' => Member::factory(),
            'blocked_id' => Member::factory(),
        ];
    }
}
