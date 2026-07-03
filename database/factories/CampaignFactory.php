<?php

namespace Database\Factories;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform' => fake()->randomElement(['meta', 'google']),
            'external_id' => fake()->optional()->numerify('##########'),
            'name' => fake()->unique()->words(3, true),
            'status' => 'active',
            'daily_budget' => fake()->optional()->randomFloat(2, 50, 500),
            'target_cpa' => fake()->optional()->randomFloat(2, 10, 80),
            'target_roas' => fake()->optional()->randomFloat(2, 1, 5),
        ];
    }
}
