<?php

use App\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Models\Campaign;
use App\Models\RecommendedAction;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('is "not_analyzed" when the campaign has never been through growthops:analyze', function () {
    $campaign = Campaign::factory()->create(['last_analyzed_at' => null]);

    expect($campaign->analysis_status)->toBe('not_analyzed');
});

it('is "healthy" when analyzed with no pending actions', function () {
    $campaign = Campaign::factory()->create(['last_analyzed_at' => now()]);

    expect($campaign->analysis_status)->toBe('healthy');
});

it('is "requires_action" when analyzed with at least one pending action', function () {
    $campaign = Campaign::factory()->create(['last_analyzed_at' => now()]);
    RecommendedAction::factory()->for($campaign)->create(['status' => 'pending']);

    expect($campaign->fresh()->analysis_status)->toBe('requires_action');
});

it('is "healthy" when analyzed and the only action on record has already been decided', function () {
    $campaign = Campaign::factory()->create(['last_analyzed_at' => now()]);
    RecommendedAction::factory()->for($campaign)->create(['status' => 'approved']);

    expect($campaign->fresh()->analysis_status)->toBe('healthy');
});

it('reflects the same status whether or not the withActionStatus scope preloaded the count', function () {
    $campaign = Campaign::factory()->create(['last_analyzed_at' => now()]);
    RecommendedAction::factory()->for($campaign)->create(['status' => 'pending']);

    $preloaded = Campaign::query()->withActionStatus()->firstOrFail();
    $lazy = Campaign::query()->firstOrFail();

    expect($preloaded->analysis_status)->toBe('requires_action')
        ->and($lazy->analysis_status)->toBe('requires_action');
});

it('renders the status badge on the campaigns table for all three states', function () {
    config(['app.key' => 'base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=']);
    actingAs(User::factory()->create());

    $notAnalyzed = Campaign::factory()->create(['last_analyzed_at' => null]);
    $healthy = Campaign::factory()->create(['last_analyzed_at' => now()]);
    $requiresAction = Campaign::factory()->create(['last_analyzed_at' => now()]);
    RecommendedAction::factory()->for($requiresAction)->create(['status' => 'pending']);

    Livewire::test(ListCampaigns::class)
        ->assertOk()
        ->assertSee('Not analyzed yet')
        ->assertSee('Healthy')
        ->assertSee('Requires action');
});

it('renders a platform badge for every known platform without error', function () {
    config(['app.key' => 'base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=']);
    actingAs(User::factory()->create());

    foreach (['google', 'meta', 'tiktok', 'taboola'] as $platform) {
        Campaign::factory()->create(['platform' => $platform]);
    }

    Livewire::test(ListCampaigns::class)
        ->assertOk()
        ->assertSee('Google')
        ->assertSee('Meta')
        ->assertSee('TikTok')
        ->assertSee('Taboola');
});
