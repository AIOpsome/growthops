<?php

use App\Models\ActionAudit;
use App\Models\Campaign;
use App\Models\ExecutionLog;
use App\Models\RecommendedAction;
use Illuminate\Support\Facades\Artisan;

it('wipes existing data and rebuilds the canonical 7-campaign demo story', function () {
    Campaign::factory()->count(3)->create();

    Artisan::call('growthops:demo-reset');

    expect(Campaign::query()->count())->toBe(7)
        ->and(RecommendedAction::query()->where('status', 'pending')->count())->toBe(3);
});

it('is idempotent when reset repeatedly', function () {
    Artisan::call('growthops:demo-reset');
    Artisan::call('growthops:demo-reset');

    expect(Campaign::query()->count())->toBe(7);
});

it('returns 404 for the reset route when no token is configured', function () {
    config(['growthops.demo_reset.token' => null]);

    $this->get('/internal/demo-reset/anything')->assertNotFound();
});

it('returns 404 for the reset route with the wrong token', function () {
    config(['growthops.demo_reset.token' => 'the-real-token']);

    $this->get('/internal/demo-reset/wrong-token')->assertNotFound();
    $this->post('/internal/demo-reset/wrong-token')->assertNotFound();
});

it('shows the reset page and triggers a reset with the correct token', function () {
    config(['growthops.demo_reset.token' => 'the-real-token']);

    Campaign::factory()->count(2)->create();

    $this->get('/internal/demo-reset/the-real-token')
        ->assertOk()
        ->assertSee('Reset demo data');

    $this->post('/internal/demo-reset/the-real-token')
        ->assertRedirect(route('demo-reset.show', ['token' => 'the-real-token']));

    expect(Campaign::query()->count())->toBe(7);
});

it('deletes execution logs and audits along with everything else', function () {
    config(['growthops.demo_reset.token' => 'the-real-token']);

    Artisan::call('growthops:demo-reset');
    $action = RecommendedAction::query()->first();
    ActionAudit::query()->create(['recommended_action_id' => $action->id, 'actor' => 'demo@growthops.test', 'from_status' => 'pending', 'to_status' => 'approved']);
    ExecutionLog::query()->create(['recommended_action_id' => $action->id, 'status' => 'simulated', 'platform' => $action->campaign->platform, 'simulated_endpoint' => 'test', 'simulated_payload' => []]);

    $this->post('/internal/demo-reset/the-real-token');

    expect(ActionAudit::query()->count())->toBe(0)
        ->and(ExecutionLog::query()->count())->toBe(0);
});
