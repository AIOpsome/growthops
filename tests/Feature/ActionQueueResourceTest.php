<?php

use App\Filament\Resources\RecommendedActions\Pages\ListRecommendedActions;
use App\Filament\Resources\RecommendedActions\Pages\ViewRecommendedAction;
use App\Models\Campaign;
use App\Models\DailyMetric;
use App\Models\RecommendedAction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders the action queue list with seeded actions', function () {
    $actions = RecommendedAction::factory()
        ->for(Campaign::factory())
        ->sequence(
            ['type' => 'pause'],
            ['type' => 'scale'],
            ['type' => 'investigate'],
        )
        ->count(3)
        ->create();

    Livewire::test(ListRecommendedActions::class)
        ->assertOk()
        ->assertCanSeeTableRecords($actions);
});

it('shows the evidence payload including the Meta caveat on the view page', function () {
    $action = RecommendedAction::factory()->for(Campaign::factory()->meta())->create([
        'type' => 'pause',
        'evidence' => [
            'detector' => 'budget_bleeder',
            'recent_spend' => 350.0,
            'caveat' => 'meta_72h_provisional',
            'provisional_days' => ['2026-06-30', '2026-06-29', '2026-06-28'],
        ],
    ]);

    Livewire::test(ViewRecommendedAction::class, ['record' => $action->getRouteKey()])
        ->assertOk()
        ->assertSee('meta_72h_provisional')
        ->assertSee('budget_bleeder');
});

it('runs the detector engine from the browser via the "Run daily analysis" action', function () {
    $campaign = Campaign::factory()->google()->create();

    foreach (range(13, 7) as $offset) {
        DailyMetric::factory()->for($campaign)->create([
            'date' => now()->subDays($offset)->toDateString(),
            'spend' => 500,
            'conversions' => 12,
        ]);
    }

    foreach (range(6, 0) as $offset) {
        DailyMetric::factory()->for($campaign)->create([
            'date' => now()->subDays($offset)->toDateString(),
            'spend' => 500,
            'conversions' => 0,
        ]);
    }

    expect(RecommendedAction::query()->count())->toBe(0);

    Livewire::test(ListRecommendedActions::class)
        ->callAction('runAnalysis')
        ->assertOk();

    expect(RecommendedAction::query()->where('campaign_id', $campaign->id)->where('type', 'pause')->exists())->toBeTrue();
});
