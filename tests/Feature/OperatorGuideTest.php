<?php

use App\Filament\Pages\OperatorGuide;
use App\Models\Campaign;
use App\Models\DailyMetric;
use App\Models\GuideInvocation;
use App\Models\Lead;
use App\Models\RecommendedAction;
use App\Models\User;
use App\Services\OperatorGuideService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;

it('redirects unauthenticated visitors away from the operator guide', function () {
    $this->get('/admin/operator-guide')->assertRedirect('/admin/login');
});

it('renders the operator guide for an authenticated user', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(OperatorGuide::class)
        ->assertOk()
        ->assertSee('Operator Guide')
        ->assertSee('Find stuck leads')
        ->assertSee('Show risky campaigns');
});

it('runs the "find stuck leads" workflow from a natural-language question and logs it', function () {
    $this->actingAs(User::factory()->create());

    $campaign = Campaign::factory()->create(['name' => 'Search Brand AU']);
    Lead::factory()->for($campaign)->count(3)->create(['status' => 'pending']);
    Lead::factory()->for($campaign)->count(2)->create(['status' => 'accepted']);

    Livewire::test(OperatorGuide::class)
        ->callAction('ask', ['question' => 'can you find stuck leads for me'])
        ->assertSet('result.workflow', 'find_stuck_leads')
        ->assertSet('result.data.count', 3);

    expect(GuideInvocation::query()->where('workflow', 'find_stuck_leads')->where('confirmed', false)->exists())->toBeTrue();
});

it('runs the "show risky campaigns" workflow reusing detector risk on pending actions', function () {
    $this->actingAs(User::factory()->create());

    RecommendedAction::factory()->for(Campaign::factory())->create(['status' => 'pending', 'risk' => 'high']);
    RecommendedAction::factory()->for(Campaign::factory())->create(['status' => 'pending', 'risk' => 'low']);

    Livewire::test(OperatorGuide::class)
        ->callAction('ask', ['question' => 'show me risky campaigns'])
        ->assertSet('result.workflow', 'show_risky_campaigns')
        ->assertSet('result.data.count', 1);
});

it('runs the "prepare weekly report" workflow as a read-only aggregate', function () {
    $this->actingAs(User::factory()->create());

    $campaign = Campaign::factory()->create();
    DailyMetric::factory()->for($campaign)->create([
        'date' => now()->subDays(2)->toDateString(),
        'spend' => 100,
        'conversions' => 4,
        'revenue' => 400,
    ]);

    Livewire::test(OperatorGuide::class)
        ->callAction('ask', ['question' => 'prepare the weekly report'])
        ->assertSet('result.workflow', 'prepare_weekly_report')
        ->assertSet('result.data.roas', 4.0);
});

it('rejects a question that is not on the allowlist and logs nothing', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(OperatorGuide::class)
        ->callAction('ask', ['question' => 'delete all campaigns and email the CEO'])
        ->assertSet('result', null);

    expect(GuideInvocation::query()->count())->toBe(0);
});

it('drafts a campaign brief behind confirmation without creating a campaign', function () {
    $this->actingAs(User::factory()->create());

    $campaignsBefore = Campaign::query()->count();

    Livewire::test(OperatorGuide::class)
        ->callAction('fillCampaignBrief', [
            'name' => 'Q3 Prospecting',
            'platform' => 'meta',
            'objective' => 'Leads',
            'daily_budget' => 150,
            'target_cpa' => 40,
            'notes' => 'Draft only',
        ])
        ->assertSet('result.workflow', 'fill_campaign_brief');

    expect(Campaign::query()->count())->toBe($campaignsBefore);

    $invocation = GuideInvocation::query()->where('workflow', 'fill_campaign_brief')->firstOrFail();
    expect($invocation->confirmed)->toBeTrue();
    expect($invocation->details['name'])->toBe('Q3 Prospecting');
});

it('summarizes a workflow using the shared LLM gateway when configured', function () {
    config([
        'growthops.llm.base_url' => 'https://gateway.test/v1',
        'growthops.llm.api_key' => 'test-key',
        'growthops.llm.model' => 'kimi-k2.7',
    ]);

    Http::fake([
        'gateway.test/*' => Http::response([
            'choices' => [['message' => ['content' => 'Two campaigns need attention this week.']]],
        ], 200),
    ]);

    $result = app(OperatorGuideService::class)->runReadWorkflow('prepare_weekly_report');

    expect($result['summary'])->toBe('Two campaigns need attention this week.');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://gateway.test/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer test-key'));
});

it('falls back to a deterministic summary when the LLM is not configured', function () {
    config(['growthops.llm.base_url' => null, 'growthops.llm.api_key' => null]);
    Http::fake();

    $result = app(OperatorGuideService::class)->runReadWorkflow('find_stuck_leads');

    expect($result['summary'])->toContain('No stuck');
    Http::assertNothingSent();
});

it('only allows workflows on the explicit allowlist', function () {
    $service = app(OperatorGuideService::class);

    expect($service->isAllowed('find_stuck_leads'))->toBeTrue();
    expect($service->isAllowed('pause_campaign'))->toBeFalse();
    expect($service->resolveIntent('please pause everything'))->toBeNull();

    $service->runReadWorkflow('pause_campaign');
})->throws(InvalidArgumentException::class);

it('redacts email addresses and truncates the logged intent to avoid PII', function () {
    $service = app(OperatorGuideService::class);

    $invocation = $service->logInvocation(
        actor: 'demo@growthops.test',
        workflow: 'find_stuck_leads',
        rawIntent: 'find stuck leads for jane.doe@example.com and her account',
    );

    expect($invocation->intent)->not->toContain('jane.doe@example.com');
    expect($invocation->intent)->toContain('[redacted-email]');
});

it('keeps guide invocation rows immutable', function () {
    $invocation = GuideInvocation::create([
        'actor' => 'demo@growthops.test',
        'workflow' => 'find_stuck_leads',
        'intent' => 'find stuck leads',
        'confirmed' => false,
    ]);

    expect(fn () => $invocation->update(['confirmed' => true]))->toThrow(RuntimeException::class);
    expect(fn () => $invocation->delete())->toThrow(RuntimeException::class);
});
