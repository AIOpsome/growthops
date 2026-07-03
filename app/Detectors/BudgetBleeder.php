<?php

namespace App\Detectors;

use App\Models\Campaign;
use App\Models\DailyMetric;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BudgetBleeder extends AbstractDetector
{
    protected function configKey(): string
    {
        return 'budget_bleeder';
    }

    /**
     * @param  Collection<int, DailyMetric>  $dailyMetrics
     */
    public function detect(Campaign $campaign, Collection $dailyMetrics, CarbonInterface $runDate): ?ActionCandidate
    {
        $t = $this->thresholds();

        $recent = $this->window($dailyMetrics, $runDate, $t['window_days']);

        if ($recent->isEmpty()) {
            return null;
        }

        $provisional = $this->provisionalDays($campaign, $runDate);
        $recentConversions = (float) $recent->sum('conversions');
        $signalConversions = (float) $this->excludeDays($recent, $provisional)->sum('conversions');
        $recentSpend = (float) $recent->sum('spend');

        $baseline = $this->window($dailyMetrics, $runDate, $t['baseline_days'], $t['window_days']);
        $baselineConversions = (float) $baseline->sum('conversions');

        $sustainedSpend = $recentSpend >= $t['min_spend'];
        $conversionsCollapsed = $signalConversions <= $t['near_zero_conversions'];
        $usedToConvert = $baselineConversions >= $t['baseline_min_conversions'];

        if (! $sustainedSpend || ! $conversionsCollapsed || ! $usedToConvert) {
            return null;
        }

        $evidence = [
            'detector' => 'budget_bleeder',
            'window_days' => $t['window_days'],
            'recent_spend' => round($recentSpend, 2),
            'recent_conversions' => round($recentConversions, 2),
            'signal_conversions' => round($signalConversions, 2),
            'baseline_days' => $t['baseline_days'],
            'baseline_conversions' => round($baselineConversions, 2),
        ];

        if ($provisional !== []) {
            $evidence['caveat'] = 'meta_72h_provisional';
            $evidence['provisional_days'] = $provisional;
            $evidence['excluded_provisional_conversions'] = round($recentConversions - $signalConversions, 2);
        }

        return new ActionCandidate(
            type: 'pause',
            evidence: $evidence,
            confidence: $this->clampConfidence($t['base_confidence'] - $this->metaPenalty($campaign)),
            risk: $t['risk'],
            expectedUpside: round($recentSpend, 2),
        );
    }
}
