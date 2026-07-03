<?php

namespace App\Detectors;

use App\Models\Campaign;
use App\Models\DailyMetric;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SpendPacingAnomaly extends AbstractDetector
{
    protected function configKey(): string
    {
        return 'spend_pacing_anomaly';
    }

    /**
     * @param  Collection<int, DailyMetric>  $dailyMetrics
     */
    public function detect(Campaign $campaign, Collection $dailyMetrics, CarbonInterface $runDate): ?ActionCandidate
    {
        $t = $this->thresholds();

        $recent = $this->window($dailyMetrics, $runDate, $t['window_days'])
            ->sortBy(fn (DailyMetric $metric): int => $metric->date->getTimestamp())
            ->values();

        if ($recent->count() < 2) {
            return null;
        }

        $latest = $recent->last();
        $prior = $recent->slice(0, $recent->count() - 1);
        $priorAverage = (float) $prior->avg('spend');

        if ($priorAverage < $t['min_baseline_spend']) {
            return null;
        }

        $latestSpend = (float) $latest->spend;
        $ratio = $latestSpend / $priorAverage;

        if ($ratio >= $t['spike_multiplier']) {
            $direction = 'spike';
        } elseif ($ratio <= $t['collapse_multiplier']) {
            $direction = 'collapse';
        } else {
            return null;
        }

        $evidence = [
            'detector' => 'spend_pacing_anomaly',
            'window_days' => $t['window_days'],
            'direction' => $direction,
            'latest_date' => $latest->date->toDateString(),
            'latest_spend' => round($latestSpend, 2),
            'baseline_average_spend' => round($priorAverage, 2),
            'ratio' => round($ratio, 2),
        ];

        return new ActionCandidate(
            type: 'investigate',
            evidence: $evidence,
            confidence: $this->clampConfidence($t['base_confidence']),
            risk: $t['risk'],
            expectedUpside: round(abs($latestSpend - $priorAverage), 2),
        );
    }
}
