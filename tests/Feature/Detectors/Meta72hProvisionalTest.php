<?php

use App\Detectors\BudgetBleeder;
use App\Models\Campaign;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->runDate = CarbonImmutable::parse('2026-06-30');
});

/**
 * All the recent conversions land inside the trailing 72h window, where Meta
 * figures are still provisional. A Meta campaign must discount that window and
 * still flag the bleed; an identical non-Meta campaign must not.
 */
function seedProvisionalOnlyConversions(Campaign $campaign, CarbonImmutable $runDate): void
{
    foreach ([0, 1, 2] as $offset) {
        metric($campaign, $runDate->subDays($offset), ['spend' => 50, 'conversions' => 10]);
    }

    foreach ([3, 4, 5, 6] as $offset) {
        metric($campaign, $runDate->subDays($offset), ['spend' => 50, 'conversions' => 0]);
    }

    metricWindow($campaign, $runDate, 14, 7, ['spend' => 10, 'conversions' => 1]);
}

it('down-weights Meta provisional conversions, fires with caveat and reduced confidence', function () {
    $campaign = Campaign::factory()->meta()->create();
    seedProvisionalOnlyConversions($campaign, $this->runDate);

    $candidate = (new BudgetBleeder)->detect($campaign, $campaign->dailyMetrics()->get(), $this->runDate);

    expect($candidate)->not->toBeNull()
        ->and($candidate->type)->toBe('pause')
        ->and($candidate->confidence)->toBe(0.7)
        ->and($candidate->evidence['recent_conversions'])->toBe(30.0)
        ->and($candidate->evidence['signal_conversions'])->toBe(0.0)
        ->and($candidate->evidence['excluded_provisional_conversions'])->toBe(30.0)
        ->and($candidate->evidence['caveat'])->toBe('meta_72h_provisional')
        ->and($candidate->evidence['provisional_days'])->toBe(['2026-06-30', '2026-06-29', '2026-06-28']);
});

it('counts the same recent conversions for a non-Meta campaign and stays silent', function () {
    $campaign = Campaign::factory()->google()->create();
    seedProvisionalOnlyConversions($campaign, $this->runDate);

    $candidate = (new BudgetBleeder)->detect($campaign, $campaign->dailyMetrics()->get(), $this->runDate);

    expect($candidate)->toBeNull();
});
