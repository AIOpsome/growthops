<?php

use App\Filament\Resources\RecommendedActions\Pages\ViewRecommendedAction;
use App\Models\ActionAudit;
use App\Models\Campaign;
use App\Models\ExecutionLog;
use App\Models\RecommendedAction;
use App\Models\User;
use App\Services\ActionDecisionService;
use App\Services\InvalidActionTransitionException;
use App\Services\SimulatedExecutionBuilder;
use Livewire\Livewire;

function pendingAction(string $platform = 'meta', string $type = 'pause', array $campaign = []): RecommendedAction
{
    return RecommendedAction::factory()
        ->for(Campaign::factory()->create([
            'platform' => $platform,
            'external_id' => '123456',
            'daily_budget' => 100.0,
            'target_cpa' => 40.0,
            ...$campaign,
        ]))
        ->create(['type' => $type, 'status' => 'pending']);
}

it('approves an action, logging a simulated execution and an audit row', function () {
    $action = pendingAction('meta', 'pause');

    app(ActionDecisionService::class)->approve($action);

    expect($action->refresh()->status)->toBe('approved');

    $log = ExecutionLog::query()->where('recommended_action_id', $action->id)->sole();
    expect($log->status)->toBe('simulated')
        ->and($log->platform)->toBe('meta')
        ->and($log->simulated_payload)->toMatchArray(['status' => 'PAUSED']);

    $audit = ActionAudit::query()->where('recommended_action_id', $action->id)->sole();
    expect($audit->from_status)->toBe('pending')
        ->and($audit->to_status)->toBe('approved')
        ->and($audit->actor)->toBe(config('growthops.approval.actor'))
        ->and($audit->reason)->toBeNull()
        ->and($audit->edited_value)->toBeNull();
});

it('rejects an action with a reason, writing an audit row but no execution log', function () {
    $action = pendingAction();

    app(ActionDecisionService::class)->reject($action, 'Off-brand creative.');

    expect($action->refresh()->status)->toBe('rejected')
        ->and(ExecutionLog::query()->where('recommended_action_id', $action->id)->exists())->toBeFalse();

    $audit = ActionAudit::query()->where('recommended_action_id', $action->id)->sole();
    expect($audit->to_status)->toBe('rejected')
        ->and($audit->reason)->toBe('Off-brand creative.');
});

it('edits then approves, preserving the original recommendation in the audit', function () {
    $action = pendingAction('meta', 'scale');

    app(ActionDecisionService::class)->editThenApprove($action, 35.0);

    $action->refresh();
    expect($action->status)->toBe('edited')
        ->and((float) $action->applied_parameter)->toBe(35.0);

    $audit = ActionAudit::query()->where('recommended_action_id', $action->id)->sole();
    expect($audit->to_status)->toBe('edited')
        ->and($audit->edited_value)->toMatchArray([
            'key' => 'scale_pct',
            'original' => 20.0,
            'value' => 35.0,
        ]);

    expect(ExecutionLog::query()->where('recommended_action_id', $action->id)->exists())->toBeTrue();
});

it('applies the edited parameter to the simulated payload', function () {
    $action = pendingAction('meta', 'scale');

    app(ActionDecisionService::class)->editThenApprove($action, 50.0);

    $log = ExecutionLog::query()->where('recommended_action_id', $action->id)->sole();

    expect($log->simulated_payload['daily_budget'])->toBe(15000);
});

it('refuses to approve, reject, or edit an action that is no longer pending', function () {
    $action = pendingAction();
    $service = app(ActionDecisionService::class);

    $service->approve($action);
    expect($action->refresh()->status)->toBe('approved');

    $auditCountBefore = ActionAudit::query()->where('recommended_action_id', $action->id)->count();
    $logCountBefore = ExecutionLog::query()->where('recommended_action_id', $action->id)->count();

    expect(fn () => $service->approve($action))->toThrow(InvalidActionTransitionException::class);
    expect(fn () => $service->reject($action, 'too late'))->toThrow(InvalidActionTransitionException::class);
    expect(fn () => $service->editThenApprove($action, 10.0))->toThrow(InvalidActionTransitionException::class);

    expect($action->refresh()->status)->toBe('approved')
        ->and(ActionAudit::query()->where('recommended_action_id', $action->id)->count())->toBe($auditCountBefore)
        ->and(ExecutionLog::query()->where('recommended_action_id', $action->id)->count())->toBe($logCountBefore);
});

it('forbids updating or deleting audit rows', function () {
    $action = pendingAction();
    app(ActionDecisionService::class)->reject($action, 'nope');

    $audit = ActionAudit::query()->where('recommended_action_id', $action->id)->sole();

    expect(fn () => $audit->update(['reason' => 'changed']))->toThrow(RuntimeException::class);
    expect(fn () => $audit->delete())->toThrow(RuntimeException::class);
    expect($audit->fresh()->reason)->toBe('nope');
});

it('shapes the simulated pause payload differently per platform', function (string $platform, string $endpointFragment, string $key) {
    $action = pendingAction($platform, 'pause');

    $simulated = app(SimulatedExecutionBuilder::class)->build($action, 0.0);

    expect($simulated['platform'])->toBe($platform)
        ->and($simulated['simulated_endpoint'])->toContain($endpointFragment)
        ->and($simulated['simulated_payload'])->toHaveKey($key);
})->with([
    'meta' => ['meta', '/v20.0/123456', 'status'],
    'google' => ['google', 'CampaignService.MutateCampaigns', 'operations'],
    'taboola' => ['taboola', '/backstage/api/1.0', 'is_active'],
    'tiktok' => ['tiktok', '/open_api/v1.3/campaign/status/update/', 'operation_status'],
]);

it('shapes the simulated payload differently per action type on meta', function (string $type, string $key) {
    $action = pendingAction('meta', $type);

    $simulated = app(SimulatedExecutionBuilder::class)->build($action, 25.0);

    expect($simulated['simulated_payload'])->toHaveKey($key);
})->with([
    'pause' => ['pause', 'daily_budget'],
    'scale' => ['scale', 'daily_budget'],
    'fix' => ['fix', 'bid_strategy'],
    'investigate' => ['investigate', 'fields'],
]);

it('rejects through the UI and requires a reason', function () {
    $this->actingAs(User::factory()->create());
    $action = pendingAction();

    Livewire::test(ViewRecommendedAction::class, ['record' => $action->getRouteKey()])
        ->callAction('reject', data: ['reason' => ''])
        ->assertHasActionErrors(['reason']);

    Livewire::test(ViewRecommendedAction::class, ['record' => $action->getRouteKey()])
        ->callAction('reject', data: ['reason' => 'Not now.'])
        ->assertHasNoActionErrors();

    expect($action->refresh()->status)->toBe('rejected');
});

it('approves through the UI', function () {
    $this->actingAs(User::factory()->create());
    $action = pendingAction('google', 'scale');

    Livewire::test(ViewRecommendedAction::class, ['record' => $action->getRouteKey()])
        ->callAction('approve');

    expect($action->refresh()->status)->toBe('approved')
        ->and(ExecutionLog::query()->where('recommended_action_id', $action->id)->exists())->toBeTrue();
});
