<?php

namespace App\Detectors;

use App\Models\Campaign;
use App\Models\DailyMetric;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface DetectorInterface
{
    /**
     * @param  Collection<int, DailyMetric>  $dailyMetrics
     */
    public function detect(Campaign $campaign, Collection $dailyMetrics, CarbonInterface $runDate): ?ActionCandidate;
}
