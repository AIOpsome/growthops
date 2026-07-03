<?php

use App\Detectors\SpendPacingAnomaly;
use App\Models\Campaign;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->runDate = CarbonImmutable::parse('2026-06-30');
});

it('fires an investigate when the latest day spend spikes beyond the band', function () {
    $campaign = Campaign::factory()->google()->create();

    metricWindow($campaign, $this->runDate, 6, 1, ['spend' => 50]);
    metric($campaign, $this->runDate, ['spend' => 200]);

    $candidate = (new SpendPacingAnomaly)->detect($campaign, $campaign->dailyMetrics()->get(), $this->runDate);

    expect($candidate)->not->toBeNull()
        ->and($candidate->type)->toBe('investigate')
        ->and($candidate->risk)->toBe('high')
        ->and($candidate->evidence['direction'])->toBe('spike')
        ->and($candidate->expectedUpside)->toBe(150.0);
});

it('stays silent when daily spend is stable', function () {
    $campaign = Campaign::factory()->google()->create();

    metricWindow($campaign, $this->runDate, 7, 0, ['spend' => 50]);

    $candidate = (new SpendPacingAnomaly)->detect($campaign, $campaign->dailyMetrics()->get(), $this->runDate);

    expect($candidate)->toBeNull();
});
