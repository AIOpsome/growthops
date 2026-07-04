<?php

namespace App\Console\Commands;

use App\Models\ActionAudit;
use App\Models\Campaign;
use App\Models\DailyMetric;
use App\Models\ExecutionLog;
use App\Models\Lead;
use App\Models\RecommendedAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('growthops:demo-reset')]
#[Description('Wipe all campaign/action data and rebuild the canonical 7-campaign demo story')]
class DemoReset extends Command
{
    public function handle(): int
    {
        ActionAudit::query()->delete();
        ExecutionLog::query()->delete();
        RecommendedAction::query()->delete();
        Lead::query()->delete();
        DailyMetric::query()->delete();
        Campaign::query()->delete();

        $this->call('growthops:demo-seed');
        $this->call('growthops:analyze');

        $this->info('Demo data reset to the canonical 7-campaign story.');

        return self::SUCCESS;
    }
}
