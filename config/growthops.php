<?php

use App\Detectors\BudgetBleeder;
use App\Detectors\CpaBreach;
use App\Detectors\ScalingWinner;
use App\Detectors\SpendPacingAnomaly;

return [

    /*
    |--------------------------------------------------------------------------
    | Detector pipeline
    |--------------------------------------------------------------------------
    |
    | Ordered list of detector classes run against every campaign. Adding a new
    | signal (e.g. a lead-quality detector once the leads table lands in #3) is
    | a matter of appending one class that implements DetectorInterface.
    |
    */

    'detectors' => [
        BudgetBleeder::class,
        ScalingWinner::class,
        CpaBreach::class,
        SpendPacingAnomaly::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta 72h conversion restatement
    |--------------------------------------------------------------------------
    |
    | Meta restates conversions/revenue for roughly three days after they are
    | reported. Conversion-based detectors exclude this trailing window from
    | the signal and reduce confidence when the campaign is on Meta.
    |
    */

    'meta' => [
        'provisional_hours' => 72,
        'confidence_penalty' => 0.2,
        'graph_version' => 'v20.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supervised approval flow
    |--------------------------------------------------------------------------
    |
    | Every recommended action is human-approved before anything would touch an
    | ad account. Execution is SIMULATED only — we construct and store the API
    | call that WOULD be made, keyed off platform + action type, and never send
    | it. Each action type exposes one numeric parameter the operator can edit
    | before approving.
    |
    */

    'approval' => [

        'actor' => 'demo@growthops.test',

        'parameters' => [
            'pause' => ['key' => 'budget_cap', 'label' => 'Budget cap (USD, 0 = full pause)', 'default' => 0.0],
            'scale' => ['key' => 'scale_pct', 'label' => 'Budget increase (%)', 'default' => 20.0],
            'fix' => ['key' => 'target_cpa', 'label' => 'Target CPA (USD)', 'default' => 50.0],
            'investigate' => ['key' => 'lookback_days', 'label' => 'Report lookback (days)', 'default' => 7.0],
        ],

        'default_daily_budget' => 100.0,
    ],

    'detector_thresholds' => [

        'budget_bleeder' => [
            'window_days' => 7,
            'baseline_days' => 14,
            'min_spend' => 200.0,
            'near_zero_conversions' => 1.0,
            'baseline_min_conversions' => 3.0,
            'base_confidence' => 0.9,
            'risk' => 'low',
        ],

        'scaling_winner' => [
            'window_days' => 7,
            'baseline_days' => 14,
            'default_target_roas' => 3.0,
            'min_spend' => 200.0,
            'trend_tolerance' => 0.95,
            'headroom_multiplier' => 0.3,
            'base_confidence' => 0.8,
            'risk' => 'medium',
        ],

        'cpa_breach' => [
            'window_days' => 7,
            'default_target_cpa' => 50.0,
            'breach_multiplier' => 1.2,
            'min_conversions' => 2.0,
            'base_confidence' => 0.8,
            'risk' => 'medium',
        ],

        'spend_pacing_anomaly' => [
            'window_days' => 7,
            'spike_multiplier' => 1.75,
            'collapse_multiplier' => 0.4,
            'min_baseline_spend' => 20.0,
            'base_confidence' => 0.6,
            'risk' => 'high',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | LLM narrative provider
    |--------------------------------------------------------------------------
    |
    | Any OpenAI-compatible chat-completions gateway works here (Opencode Go,
    | OpenRouter, etc) — only the env values change. Missing base_url/api_key
    | falls back to a deterministic template narrative, never a broken UI.
    |
    */

    'llm' => [
        'base_url' => env('LLM_BASE_URL'),
        'model' => env('LLM_MODEL', 'kimi-k2.7'),
        'api_key' => env('LLM_API_KEY'),
        // Kimi K2.7 is a reasoning model; real narration prompts (full evidence
        // JSON + structured instructions) consistently take 12-16s to respond,
        // so a 15s timeout intermittently falls back to the template. 45s gives
        // real headroom without risking a truly hung request.
        'timeout' => 45,
    ],

    /*
    |--------------------------------------------------------------------------
    | CSV import limits
    |--------------------------------------------------------------------------
    |
    | Each campaign a CSV import produces can eventually trigger one lazy LLM
    | narrative call when a human views its action. A large upload means many
    | campaigns means many narrative calls — cap file size to keep that bounded.
    |
    */

    'import' => [
        'max_csv_size_kb' => (int) env('GROWTHOPS_MAX_CSV_SIZE_KB', 2048),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo reset
    |--------------------------------------------------------------------------
    |
    | A rehearsal-only utility, deliberately NOT part of the /admin panel:
    | resets all campaign/action data back to the canonical demo-seed story
    | in one click. Gated by an unguessable token from the deploy env, never
    | committed anywhere — leaving the token unset disables the route entirely
    | (404), which is also this app's default in every environment except the
    | founder's own deploy.
    |
    */

    'demo_reset' => [
        'token' => env('DEMO_RESET_TOKEN'),
    ],

];
