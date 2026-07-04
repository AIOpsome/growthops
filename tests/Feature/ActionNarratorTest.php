<?php

use App\Models\Campaign;
use App\Models\RecommendedAction;
use App\Services\ActionNarrator;
use Illuminate\Support\Facades\Http;

it('stores the LLM narrative verbatim when the gateway responds successfully', function () {
    config([
        'growthops.llm.base_url' => 'https://gateway.test/v1',
        'growthops.llm.api_key' => 'test-key',
        'growthops.llm.model' => 'kimi-k2.7',
    ]);

    Http::fake([
        'gateway.test/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Pause this campaign: spend of $350 with zero conversions in 7 days.']],
            ],
        ], 200),
    ]);

    $action = RecommendedAction::factory()->for(Campaign::factory())->create(['narrative' => null]);

    $narrative = $action->ensureNarrative();

    expect($narrative)->toBe('Pause this campaign: spend of $350 with zero conversions in 7 days.');
    expect($action->fresh()->narrative)->toBe('Pause this campaign: spend of $350 with zero conversions in 7 days.');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://gateway.test/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer test-key'));
});

it('falls back to the template narrative when the LLM config is missing', function () {
    config([
        'growthops.llm.base_url' => null,
        'growthops.llm.api_key' => null,
    ]);

    Http::fake();

    $action = RecommendedAction::factory()->for(Campaign::factory())->create([
        'narrative' => null,
        'type' => 'pause',
        'confidence' => 0.9,
        'expected_upside' => 500,
        'risk' => 'low',
    ]);

    $narrative = $action->ensureNarrative();

    expect($narrative)->toContain('pause')->toContain('500');
    Http::assertNothingSent();
});

it('falls back to the template narrative when the LLM call fails', function () {
    config([
        'growthops.llm.base_url' => 'https://gateway.test/v1',
        'growthops.llm.api_key' => 'test-key',
    ]);

    Http::fake([
        'gateway.test/*' => Http::response(null, 500),
    ]);

    $action = RecommendedAction::factory()->for(Campaign::factory())->create([
        'narrative' => null,
        'type' => 'scale',
        'expected_upside' => 750,
    ]);

    $narrative = $action->ensureNarrative();

    expect($narrative)->toContain('scale')->toContain('750');
});

it('generates the narrative once and reuses the cached value on subsequent access', function () {
    config([
        'growthops.llm.base_url' => 'https://gateway.test/v1',
        'growthops.llm.api_key' => 'test-key',
    ]);

    Http::fake([
        'gateway.test/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Cached narrative content.']],
            ],
        ], 200),
    ]);

    $action = RecommendedAction::factory()->for(Campaign::factory())->create(['narrative' => null]);

    $action->ensureNarrative();
    $action->fresh()->ensureNarrative();

    Http::assertSentCount(1);
});

it('never calls the gateway when the narrative already exists', function () {
    Http::fake();

    $action = RecommendedAction::factory()->for(Campaign::factory())->create([
        'narrative' => 'Already generated.',
    ]);

    expect(app(ActionNarrator::class))->not->toBeNull();
    expect($action->ensureNarrative())->toBe('Already generated.');
    Http::assertNothingSent();
});

it('captures the model reasoning alongside the narrative when the gateway returns it', function () {
    config([
        'growthops.llm.base_url' => 'https://gateway.test/v1',
        'growthops.llm.api_key' => 'test-key',
    ]);

    Http::fake([
        'gateway.test/*' => Http::response([
            'choices' => [
                ['message' => [
                    'content' => 'Pause this campaign.',
                    'reasoning_content' => 'We need to weigh the 7-day spend against the baseline conversions...',
                ]],
            ],
        ], 200),
    ]);

    $action = RecommendedAction::factory()->for(Campaign::factory())->create(['narrative' => null, 'reasoning' => null]);

    $action->ensureNarrative();

    expect($action->fresh()->reasoning)->toBe('We need to weigh the 7-day spend against the baseline conversions...');
});

it('leaves reasoning null for the deterministic template fallback', function () {
    config(['growthops.llm.base_url' => null, 'growthops.llm.api_key' => null]);

    Http::fake();

    $action = RecommendedAction::factory()->for(Campaign::factory())->create(['narrative' => null, 'reasoning' => null]);

    $action->ensureNarrative();

    expect($action->fresh()->reasoning)->toBeNull();
});
