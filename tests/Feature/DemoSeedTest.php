<?php

use App\Models\Campaign;
use App\Models\RecommendedAction;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->artisan('growthops:demo-seed')->assertSuccessful();
    $this->artisan('growthops:analyze', ['--date' => CarbonImmutable::today()->toDateString()])->assertSuccessful();
});

it('seeds exactly seven campaigns across all four platforms', function () {
    expect(Campaign::query()->count())->toBe(7)
        ->and(Campaign::query()->pluck('platform')->unique()->sort()->values()->all())
        ->toBe(['google', 'meta', 'taboola', 'tiktok']);
});

it('fires a high-confidence pause for the bleeding Meta campaign with the provisional caveat', function () {
    $campaign = Campaign::query()->where('name', 'Meta Prospecting - Winter Sale')->firstOrFail();

    $action = RecommendedAction::query()->where('campaign_id', $campaign->id)->where('type', 'pause')->firstOrFail();

    expect($action->status)->toBe('pending')
        ->and((float) $action->confidence)->toBe(0.7)
        ->and($action->evidence['caveat'])->toBe('meta_72h_provisional');
});

it('fires a scale for the Google scaling-winner campaign', function () {
    $campaign = Campaign::query()->where('name', 'Google Search - Branded Terms')->firstOrFail();

    $action = RecommendedAction::query()->where('campaign_id', $campaign->id)->where('type', 'scale')->firstOrFail();

    expect($action->status)->toBe('pending')
        ->and((float) $action->evidence['roas'])->toBeGreaterThanOrEqual(3.5);
});

it('fires a fix for the TikTok CPA-breach campaign', function () {
    $campaign = Campaign::query()->where('name', 'TikTok Spark Ads - UGC Creators')->firstOrFail();

    $action = RecommendedAction::query()->where('campaign_id', $campaign->id)->where('type', 'fix')->firstOrFail();

    expect($action->status)->toBe('pending')
        ->and((float) $action->evidence['cpa'])->toBeGreaterThanOrEqual(60.0);
});

it('produces no action for the Taboola lead-quality campaign and shows the acceptance-rate collapse in the raw data', function () {
    $campaign = Campaign::query()->where('name', 'Taboola Native - Homepage Placements')->firstOrFail();

    expect(RecommendedAction::query()->where('campaign_id', $campaign->id)->count())->toBe(0);

    $baselineLeads = $campaign->leads()->where('date', '<=', CarbonImmutable::today()->subDays(7)->toDateString())->get();
    $recentLeads = $campaign->leads()->where('date', '>', CarbonImmutable::today()->subDays(7)->toDateString())->get();

    $rate = fn ($leads): float => $leads->whereIn('status', ['accepted', 'rejected'])->count() > 0
        ? $leads->where('status', 'accepted')->count() / $leads->whereIn('status', ['accepted', 'rejected'])->count() * 100
        : 0.0;

    expect($rate($baselineLeads))->toBeGreaterThan(70.0)
        ->and($rate($recentLeads))->toBeLessThan(35.0);
});

it('holds fire on the Meta campaign that only looks like a bleeder inside the 72h provisional window', function () {
    $campaign = Campaign::query()->where('name', 'Meta Retargeting - Cart Abandoners')->firstOrFail();

    expect(RecommendedAction::query()->where('campaign_id', $campaign->id)->count())->toBe(0);
});

it('produces zero actions for the healthy campaigns', function () {
    $healthy = Campaign::query()->whereIn('name', [
        'Google Performance Max - Catalog Sales',
        'TikTok Awareness - Video Views',
    ])->get();

    expect($healthy)->toHaveCount(2);

    foreach ($healthy as $campaign) {
        expect(RecommendedAction::query()->where('campaign_id', $campaign->id)->count())->toBe(0);
    }
});

it('is idempotent when reseeded and reanalyzed', function () {
    $this->artisan('growthops:demo-seed')->assertSuccessful();
    $this->artisan('growthops:analyze', ['--date' => CarbonImmutable::today()->toDateString()])->assertSuccessful();

    expect(Campaign::query()->count())->toBe(7)
        ->and(RecommendedAction::query()->count())->toBe(3);
});
