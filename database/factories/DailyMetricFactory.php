<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\DailyMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyMetric>
 */
class DailyMetricFactory extends Factory
{
    protected $model = DailyMetric::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clicks = fake()->numberBetween(50, 5000);
        $conversions = fake()->randomFloat(2, 0, 250);

        return [
            'campaign_id' => Campaign::factory(),
            'date' => fake()->date(),
            'spend' => fake()->randomFloat(2, 20, 800),
            'impressions' => fake()->numberBetween(1000, 200000),
            'clicks' => $clicks,
            'conversions' => $conversions,
            'revenue' => fake()->randomFloat(2, 0, 5000),
        ];
    }
}
