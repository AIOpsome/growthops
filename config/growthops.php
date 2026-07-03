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

];
