<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'external_id' => fake()->unique()->uuid(),
            'date' => fake()->date(),
            'status' => fake()->randomElement(['accepted', 'rejected', 'pending']),
            'revenue' => fake()->randomFloat(2, 0, 500),
        ];
    }
}
