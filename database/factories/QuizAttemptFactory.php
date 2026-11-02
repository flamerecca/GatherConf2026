<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\PersonalityType;
use App\Models\QuizAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QuizAttempt>
 */
class QuizAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'user_id' => null,
            'scores' => ['NM' => 0, 'SV' => 0, 'HA' => 0, 'GD' => 0],
            'max_scores' => ['NM' => 6, 'SV' => 6, 'HA' => 6, 'GD' => 6],
            'type_code' => fn (): string => PersonalityType::query()->value('code') ?? PersonalityType::query()->create([
                'code' => 'NVAD', 'name' => '測試類型', 'summary' => '測試摘要', 'description' => '測試說明',
            ])->code,
            'completed_at' => now(),
        ];
    }

    public function forMember(?Member $member = null): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $member?->getKey() ?? Member::factory()]);
    }
}
