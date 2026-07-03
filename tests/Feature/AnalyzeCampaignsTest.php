<?php

use App\Models\Campaign;
use App\Models\RecommendedAction;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->runDate = CarbonImmutable::parse('2026-06-30');
    $this->campaign = Campaign::factory()->google()->create();

    metricWindow($this->campaign, $this->runDate, 7, 0, ['spend' => 50, 'conversions' => 0]);
    metricWindow($this->campaign, $this->runDate, 14, 7, ['spend' => 10, 'conversions' => 1]);
});

it('generates a pending pause action for the bleeding campaign', function () {
    $this->artisan('growthops:analyze', ['--date' => '2026-06-30'])->assertSuccessful();

    expect(RecommendedAction::query()->where('type', 'pause')->where('status', 'pending')->count())->toBe(1);
});

it('is idempotent across re-runs and never duplicates', function () {
    $this->artisan('growthops:analyze', ['--date' => '2026-06-30'])->assertSuccessful();
    $this->artisan('growthops:analyze', ['--date' => '2026-06-30'])->assertSuccessful();

    expect(RecommendedAction::query()->count())->toBe(1);
});

it('never touches actions that have already been decided', function () {
    $this->artisan('growthops:analyze', ['--date' => '2026-06-30'])->assertSuccessful();

    RecommendedAction::query()->firstOrFail()->update(['status' => 'approved']);

    $this->artisan('growthops:analyze', ['--date' => '2026-06-30'])->assertSuccessful();

    $action = RecommendedAction::query()->firstOrFail();

    expect(RecommendedAction::query()->count())->toBe(1)
        ->and($action->status)->toBe('approved');
});

it('removes a stale pending action that no longer fires', function () {
    $stale = RecommendedAction::factory()->for($this->campaign)->create([
        'run_date' => '2026-06-30',
        'type' => 'scale',
        'status' => 'pending',
    ]);

    $this->artisan('growthops:analyze', ['--date' => '2026-06-30'])->assertSuccessful();

    expect(RecommendedAction::query()->find($stale->id))->toBeNull()
        ->and(RecommendedAction::query()->where('type', 'pause')->exists())->toBeTrue();
});
