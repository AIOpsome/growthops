<?php

namespace App\Console\Commands;

use App\Detectors\DetectorEngine;
use App\Models\Campaign;
use App\Models\RecommendedAction;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('growthops:analyze {--date= : Analysis date (Y-m-d), defaults to today}')]
#[Description('Generate the daily recommended-action queue from detector signals')]
class AnalyzeCampaigns extends Command
{
    public function handle(DetectorEngine $engine): int
    {
        $runDate = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))->startOfDay()
            : CarbonImmutable::today();

        $generated = 0;

        Campaign::query()->with('dailyMetrics')->each(function (Campaign $campaign) use ($engine, $runDate, &$generated): void {
            $keptTypes = [];

            foreach ($engine->analyze($campaign, $runDate) as $candidate) {
                $keptTypes[] = $candidate->type;

                $action = RecommendedAction::query()->firstOrNew([
                    'campaign_id' => $campaign->id,
                    'run_date' => $runDate,
                    'type' => $candidate->type,
                ]);

                if ($action->exists && $action->status !== 'pending') {
                    continue;
                }

                $action->fill([
                    'evidence' => $candidate->evidence,
                    'confidence' => $candidate->confidence,
                    'risk' => $candidate->risk,
                    'expected_upside' => $candidate->expectedUpside,
                    'status' => 'pending',
                ])->save();

                $generated++;
            }

            RecommendedAction::query()
                ->where('campaign_id', $campaign->id)
                ->where('run_date', $runDate)
                ->where('status', 'pending')
                ->whereNotIn('type', $keptTypes)
                ->delete();

            $campaign->update(['last_analyzed_at' => now()]);
        });

        $this->info("Generated {$generated} recommended action(s) for {$runDate->toDateString()}.");

        return self::SUCCESS;
    }
}
