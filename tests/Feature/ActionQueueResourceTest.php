<?php

use App\Filament\Resources\RecommendedActions\Pages\ListRecommendedActions;
use App\Filament\Resources\RecommendedActions\Pages\ViewRecommendedAction;
use App\Models\Campaign;
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
