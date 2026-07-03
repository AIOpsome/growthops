<?php

namespace App\Detectors;

use App\Models\Campaign;
use Carbon\CarbonInterface;

class DetectorEngine
{
    /**
     * Run the configured detector pipeline against a single campaign.
     *
     * @return list<ActionCandidate>
     */
    public function analyze(Campaign $campaign, CarbonInterface $runDate): array
    {
        $metrics = $campaign->dailyMetrics;
        $candidates = [];

        foreach (config('growthops.detectors') as $class) {
            $detector = app($class);
            $candidate = $detector->detect($campaign, $metrics, $runDate);

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }
}
