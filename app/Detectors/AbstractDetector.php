<?php

namespace App\Detectors;

use App\Models\Campaign;
use App\Models\DailyMetric;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

abstract class AbstractDetector implements DetectorInterface
{
    abstract protected function configKey(): string;

    /**
     * @return array<string, mixed>
     */
    protected function thresholds(): array
    {
        return config('growthops.detector_thresholds.'.$this->configKey());
    }

    /**
     * Trailing window of $days ending $offsetDays before the run date.
     *
     * @param  Collection<int, DailyMetric>  $metrics
     * @return Collection<int, DailyMetric>
     */
    protected function window(Collection $metrics, CarbonInterface $runDate, int $days, int $offsetDays = 0): Collection
    {
        $end = $runDate->subDays($offsetDays);
        $start = $end->subDays($days - 1);

        return $metrics->filter(fn (DailyMetric $metric): bool => $metric->date->between($start, $end));
    }

    /**
     * Days whose conversion figures Meta may still restate.
     *
     * @return list<string>
     */
    protected function provisionalDays(Campaign $campaign, CarbonInterface $runDate): array
    {
        if ($campaign->platform !== 'meta') {
            return [];
        }

        $days = (int) ceil((int) config('growthops.meta.provisional_hours') / 24);

        return collect(range(0, $days - 1))
            ->map(fn (int $offset): string => $runDate->subDays($offset)->toDateString())
            ->all();
    }

    /**
     * @param  Collection<int, DailyMetric>  $metrics
     * @param  list<string>  $days
     * @return Collection<int, DailyMetric>
     */
    protected function excludeDays(Collection $metrics, array $days): Collection
    {
        return $metrics->reject(fn (DailyMetric $metric): bool => in_array($metric->date->toDateString(), $days, true));
    }

    protected function metaPenalty(Campaign $campaign): float
    {
        return $campaign->platform === 'meta'
            ? (float) config('growthops.meta.confidence_penalty')
            : 0.0;
    }

    protected function clampConfidence(float $confidence): float
    {
        return round(max(0.0, min(1.0, $confidence)), 2);
    }
}
