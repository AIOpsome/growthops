<?php

use App\Detectors\CpaBreach;
use App\Models\Campaign;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->runDate = CarbonImmutable::parse('2026-06-30');
});

it('fires a fix when CPA consistently exceeds target', function () {
    $campaign = Campaign::factory()->google()->create(['target_cpa' => 50]);

    metricWindow($campaign, $this->runDate, 7, 0, ['spend' => 100, 'conversions' => 1, 'revenue' => 60]);

    $candidate = (new CpaBreach)->detect($campaign, $campaign->dailyMetrics()->get(), $this->runDate);

    expect($candidate)->not->toBeNull()
        ->and($candidate->type)->toBe('fix')
        ->and($candidate->risk)->toBe('medium')
        ->and($candidate->confidence)->toBe(0.8)
        ->and($candidate->evidence['cpa'])->toBe(100.0)
        ->and($candidate->expectedUpside)->toBe(350.0);
});

it('stays silent when CPA is within target', function () {
    $campaign = Campaign::factory()->google()->create(['target_cpa' => 50]);

    metricWindow($campaign, $this->runDate, 7, 0, ['spend' => 100, 'conversions' => 20, 'revenue' => 600]);

    $candidate = (new CpaBreach)->detect($campaign, $campaign->dailyMetrics()->get(), $this->runDate);

    expect($candidate)->toBeNull();
});
