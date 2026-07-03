<?php

use App\Detectors\BudgetBleeder;
use App\Models\Campaign;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->runDate = CarbonImmutable::parse('2026-06-30');
});

it('fires a pause when sustained spend has near-zero conversions versus baseline', function () {
    $campaign = Campaign::factory()->google()->create();

    metricWindow($campaign, $this->runDate, 7, 0, ['spend' => 50, 'conversions' => 0]);
    metricWindow($campaign, $this->runDate, 14, 7, ['spend' => 10, 'conversions' => 1]);

    $candidate = (new BudgetBleeder)->detect($campaign, $campaign->dailyMetrics()->get(), $this->runDate);

    expect($candidate)->not->toBeNull()
        ->and($candidate->type)->toBe('pause')
        ->and($candidate->risk)->toBe('low')
        ->and($candidate->confidence)->toBe(0.9)
        ->and($candidate->expectedUpside)->toBe(350.0)
        ->and($candidate->evidence['detector'])->toBe('budget_bleeder');
});

it('stays silent when the campaign is still converting', function () {
    $campaign = Campaign::factory()->google()->create();

    metricWindow($campaign, $this->runDate, 7, 0, ['spend' => 50, 'conversions' => 5]);
    metricWindow($campaign, $this->runDate, 14, 7, ['spend' => 10, 'conversions' => 1]);

    $candidate = (new BudgetBleeder)->detect($campaign, $campaign->dailyMetrics()->get(), $this->runDate);

    expect($candidate)->toBeNull();
});
