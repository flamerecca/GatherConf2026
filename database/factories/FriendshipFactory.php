<?php

namespace Database\Factories;

use App\Models\Friendship;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Friendship>
 */
class FriendshipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->pair(Member::factory()->create(), Member::factory()->create()) + [
            'status' => Friendship::STATUS_PENDING,
            'responded_at' => null,
        ];
    }

    public function between(Member $requester, Member $other): static
    {
        return $this->state(fn (array $attributes) => $this->pair($requester, $other));
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Friendship::STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);
    }

    /**
     * @return array{member_low_id: int, member_high_id: int, requester_id: int}
     */
    private function pair(Member $requester, Member $other): array
    {
        [$low, $high] = Friendship::orderedIds($requester->getKey(), $other->getKey());

        return [
            'member_low_id' => $low,
            'member_high_id' => $high,
            'requester_id' => $requester->getKey(),
        ];
    }
}
