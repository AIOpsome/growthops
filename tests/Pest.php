<?php

use App\Models\Campaign;
use App\Models\DailyMetric;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Seed one daily-metric row.
 *
 * @param  array<string, mixed>  $attributes
 */
function metric(Campaign $campaign, CarbonImmutable $date, array $attributes): DailyMetric
{
    return DailyMetric::factory()->for($campaign)->create([
        'date' => $date->toDateString(),
        'spend' => 0,
        'impressions' => 5000,
        'clicks' => 100,
        'conversions' => 0,
        'revenue' => 0,
        ...$attributes,
    ]);
}

/**
 * Seed a contiguous block of daily metrics ending $offset days before $runDate.
 *
 * @param  array<string, mixed>  $attributes
 */
function metricWindow(Campaign $campaign, CarbonImmutable $runDate, int $days, int $offset, array $attributes): void
{
    foreach (range(0, $days - 1) as $index) {
        metric($campaign, $runDate->subDays($offset + $index), $attributes);
    }
}
