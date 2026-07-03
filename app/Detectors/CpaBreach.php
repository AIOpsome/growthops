<?php

namespace App\Detectors;

use App\Models\Campaign;
use App\Models\DailyMetric;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CpaBreach extends AbstractDetector
{
    protected function configKey(): string
    {
        return 'cpa_breach';
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
        $stable = $this->excludeDays($recent, $provisional);

        $conversions = (float) $stable->sum('conversions');
        $spend = (float) $stable->sum('spend');

        if ($conversions < $t['min_conversions']) {
            return null;
        }

        $cpa = $spend / $conversions;
        $target = $campaign->target_cpa !== null ? (float) $campaign->target_cpa : (float) $t['default_target_cpa'];

        if ($cpa < $target * $t['breach_multiplier']) {
            return null;
        }

        $expectedUpside = $spend - ($target * $conversions);

        $evidence = [
            'detector' => 'cpa_breach',
            'window_days' => $t['window_days'],
            'cpa' => round($cpa, 2),
            'target_cpa' => round($target, 2),
            'breach_multiplier' => $t['breach_multiplier'],
            'conversions' => round($conversions, 2),
            'spend' => round($spend, 2),
        ];

        if ($provisional !== []) {
            $evidence['caveat'] = 'meta_72h_provisional';
            $evidence['provisional_days'] = $provisional;
        }

        return new ActionCandidate(
            type: 'fix',
            evidence: $evidence,
            confidence: $this->clampConfidence($t['base_confidence'] - $this->metaPenalty($campaign)),
            risk: $t['risk'],
            expectedUpside: round($expectedUpside, 2),
        );
    }
}
