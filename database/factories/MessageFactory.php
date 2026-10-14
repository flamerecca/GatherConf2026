<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
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
            'recipient_id' => Member::factory(),
            'body' => fake()->sentence(),
            'read_at' => null,
        ];
    }

    public function from(Member $sender, Member $recipient): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_id' => $sender->getKey(),
            'recipient_id' => $recipient->getKey(),
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => ['read_at' => now()]);
    }
}
