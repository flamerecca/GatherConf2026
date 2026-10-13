<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberLoginLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MemberLoginLink>
 */
class MemberLoginLinkFactory extends Factory
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
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addMinutes(15),
            'used_at' => null,
            'revoked_at' => null,
        ];
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => ['used_at' => now()->subMinute()]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subMinute()]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => ['revoked_at' => now()->subMinute()]);
    }
}
