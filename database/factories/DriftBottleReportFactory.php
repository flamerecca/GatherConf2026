<?php

namespace Database\Factories;

use App\Models\DriftBottle;
use App\Models\DriftBottleReport;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriftBottleReport>
 */
class DriftBottleReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bottle_id' => DriftBottle::factory(),
            'reporter_id' => Member::factory(),
            'target' => 'bottle',
        ];
    }
}
