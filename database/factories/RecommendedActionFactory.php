<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\RecommendedAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendedAction>
 */
class RecommendedActionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'run_date' => now()->toDateString(),
            'type' => fake()->randomElement(['pause', 'scale', 'investigate', 'fix']),
            'evidence' => ['window_days' => 7, 'note' => fake()->sentence()],
            'confidence' => fake()->randomFloat(2, 0.5, 1),
            'risk' => fake()->randomElement(['low', 'medium', 'high']),
            'expected_upside' => fake()->randomFloat(2, 100, 5000),
            'status' => 'pending',
            'narrative' => null,
        ];
    }
}
