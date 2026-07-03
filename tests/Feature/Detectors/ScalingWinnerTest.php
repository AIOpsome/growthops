<?php

use App\Detectors\ScalingWinner;
use App\Models\Campaign;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->runDate = CarbonImmutable::parse('2026-06-30');
});

it('fires a scale when ROAS beats target with a stable trend', function () {
    $campaign = Campaign::factory()->google()->create(['target_roas' => 3]);

    metricWindow($campaign, $this->runDate, 7, 0, ['spend' => 100, 'revenue' => 400, 'conversions' => 8]);
    metricWindow($campaign, $this->runDate, 14, 7, ['spend' => 100, 'revenue' => 400, 'conversions' => 8]);

    $candidate = (new ScalingWinner)->detect($campaign, $campaign->dailyMetrics()->get(), $this->runDate);

    expect($candidate)->not->toBeNull()
        ->and($candidate->type)->toBe('scale')
        ->and($candidate->risk)->toBe('medium')
        ->and($candidate->confidence)->toBe(0.8)
        ->and($candidate->expectedUpside)->toBe(630.0)
        ->and($candidate->evidence['roas'])->toBe(4.0);
});

it('stays silent when ROAS is below target', function () {
    $campaign = Campaign::factory()->google()->create(['target_roas' => 3]);

    metricWindow($campaign, $this->runDate, 7, 0, ['spend' => 100, 'revenue' => 200, 'conversions' => 8]);
    metricWindow($campaign, $this->runDate, 14, 7, ['spend' => 100, 'revenue' => 200, 'conversions' => 8]);

    $candidate = (new ScalingWinner)->detect($campaign, $campaign->dailyMetrics()->get(), $this->runDate);

    expect($candidate)->toBeNull();
});
