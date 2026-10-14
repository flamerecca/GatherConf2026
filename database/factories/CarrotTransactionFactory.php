<?php

namespace Database\Factories;

use App\Models\CarrotTransaction;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarrotTransaction>
 */
class CarrotTransactionFactory extends Factory
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
            'amount' => 1,
            'reason' => 'manual',
            'dedupe_key' => 'manual:'.fake()->uuid(),
        ];
    }
}
