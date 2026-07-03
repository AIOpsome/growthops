<?php

namespace App\Detectors;

use App\Models\Campaign;
use App\Models\DailyMetric;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ScalingWinner extends AbstractDetector
{
    protected function configKey(): string
    {
        return 'scaling_winner';
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

        $recentSpend = (float) $recent->sum('spend');

        if ($recentSpend < $t['min_spend']) {
            return null;
        }

        $provisional = $this->provisionalDays($campaign, $runDate);
        $stable = $this->excludeDays($recent, $provisional);
        $stableSpend = (float) $stable->sum('spend');
        $stableRevenue = (float) $stable->sum('revenue');

        if ($stableSpend <= 0.0) {
            return null;
        }

        $roas = $stableRevenue / $stableSpend;
        $target = $campaign->target_roas !== null ? (float) $campaign->target_roas : (float) $t['default_target_roas'];

        if ($roas < $target) {
            return null;
        }

        $baseline = $this->window($dailyMetrics, $runDate, $t['baseline_days'], $t['window_days']);
        $baselineSpend = (float) $baseline->sum('spend');
        $baselineRoas = $baselineSpend > 0.0 ? (float) $baseline->sum('revenue') / $baselineSpend : 0.0;

        if ($baselineRoas > 0.0 && $roas < $baselineRoas * $t['trend_tolerance']) {
            return null;
        }

        $headroomSpend = $recentSpend * $t['headroom_multiplier'];
        $expectedUpside = $headroomSpend * ($roas - 1.0);

        $evidence = [
            'detector' => 'scaling_winner',
            'window_days' => $t['window_days'],
            'roas' => round($roas, 2),
            'target_roas' => round($target, 2),
            'baseline_roas' => round($baselineRoas, 2),
            'recent_spend' => round($recentSpend, 2),
            'headroom_spend' => round($headroomSpend, 2),
        ];

        if ($provisional !== []) {
            $evidence['caveat'] = 'meta_72h_provisional';
            $evidence['provisional_days'] = $provisional;
        }

        return new ActionCandidate(
            type: 'scale',
            evidence: $evidence,
            confidence: $this->clampConfidence($t['base_confidence'] - $this->metaPenalty($campaign)),
            risk: $t['risk'],
            expectedUpside: round($expectedUpside, 2),
        );
    }
}
