<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\DailyMetric;
use App\Models\Lead;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('growthops:demo-seed')]
#[Description('Seed the 7-campaign demo story (bleeder, winner, lead collapse, CPA breach, Meta 72h caveat, healthy)')]
class DemoSeed extends Command
{
    private const DAYS = 14;

    public function handle(): int
    {
        $today = CarbonImmutable::today();

        $this->seedCampaign($today, 'meta', 'Meta Prospecting - Winter Sale', [], fn (int $offset): array => $offset <= 6
            ? ['spend' => 500, 'conversions' => 0, 'revenue' => 0, 'impressions' => 95000, 'clicks' => 2600]
            : ['spend' => 500, 'conversions' => 12, 'revenue' => 1800, 'impressions' => 100000, 'clicks' => 3000]);

        $this->seedCampaign($today, 'google', 'Google Search - Branded Terms', ['target_roas' => 3.0], fn (int $offset): array => [
            'spend' => 300, 'conversions' => 15, 'revenue' => 1200, 'impressions' => 50000, 'clicks' => 2000,
        ]);

        $taboola = $this->seedCampaign($today, 'taboola', 'Taboola Native - Homepage Placements', [], fn (int $offset): array => [
            'spend' => 200, 'conversions' => 8, 'revenue' => 300, 'impressions' => 40000, 'clicks' => 1500,
        ]);
        $this->seedLeadCollapse($taboola, $today);

        $this->seedCampaign($today, 'tiktok', 'TikTok Spark Ads - UGC Creators', ['target_cpa' => 50], fn (int $offset): array => [
            'spend' => 300, 'conversions' => 30 / 7, 'revenue' => 400, 'impressions' => 60000, 'clicks' => 2200,
        ]);

        $this->seedCampaign($today, 'meta', 'Meta Retargeting - Cart Abandoners', [], fn (int $offset): array => $offset <= 2
            ? ['spend' => 500, 'conversions' => 0, 'revenue' => 0, 'impressions' => 80000, 'clicks' => 2500]
            : ['spend' => 500, 'conversions' => 10, 'revenue' => 1000, 'impressions' => 80000, 'clicks' => 2500]);

        $this->seedCampaign($today, 'google', 'Google Performance Max - Catalog Sales', [], fn (int $offset): array => [
            'spend' => 250, 'conversions' => 10, 'revenue' => 500, 'impressions' => 45000, 'clicks' => 1800,
        ]);

        $this->seedCampaign($today, 'tiktok', 'TikTok Awareness - Video Views', [], fn (int $offset): array => [
            'spend' => 150, 'conversions' => 5, 'revenue' => 200, 'impressions' => 70000, 'clicks' => 2100,
        ]);

        $this->info('Seeded 7 demo campaigns across 4 platforms with '.self::DAYS.' days of history.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seedCampaign(CarbonImmutable $today, string $platform, string $name, array $attributes, Closure $dayMetrics): Campaign
    {
        $campaign = Campaign::query()->updateOrCreate(
            ['platform' => $platform, 'name' => $name],
            [...$attributes, 'status' => 'active'],
        );

        $campaign->dailyMetrics()->delete();
        $campaign->leads()->delete();
        $campaign->recommendedActions()->delete();

        foreach (range(0, self::DAYS - 1) as $offset) {
            DailyMetric::query()->create([
                'campaign_id' => $campaign->id,
                'date' => $today->subDays($offset)->toDateString(),
                ...$dayMetrics($offset),
            ]);
        }

        return $campaign;
    }

    /**
     * Baseline week (offset 13-7): ~78% lead acceptance. Recent week (offset
     * 6-0): ~31% acceptance, spend/conversions on the campaign stay flat, so
     * the collapse is only visible in the leads themselves.
     */
    private function seedLeadCollapse(Campaign $campaign, CarbonImmutable $today): void
    {
        $baselineAccepted = [1, 1, 1, 1, 1, 1, 1];
        $baselineRejected = [0, 0, 1, 0, 0, 0, 1];
        $baselinePending = [0, 0, 0, 1, 0, 0, 0];

        foreach (range(13, 7) as $index => $offset) {
            $this->seedLeadsForDay($campaign, $today->subDays($offset), $baselineAccepted[$index], $baselineRejected[$index], $baselinePending[$index]);
        }

        $recentAccepted = [1, 0, 1, 0, 1, 0, 1];
        $recentRejected = [1, 2, 1, 1, 2, 1, 1];
        $recentPending = [0, 0, 0, 0, 0, 1, 0];

        foreach (range(6, 0) as $index => $offset) {
            $this->seedLeadsForDay($campaign, $today->subDays($offset), $recentAccepted[$index], $recentRejected[$index], $recentPending[$index]);
        }
    }

    private function seedLeadsForDay(Campaign $campaign, CarbonImmutable $date, int $accepted, int $rejected, int $pending): void
    {
        $counts = ['accepted' => $accepted, 'rejected' => $rejected, 'pending' => $pending];

        foreach ($counts as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                Lead::query()->create([
                    'campaign_id' => $campaign->id,
                    'external_id' => (string) Str::uuid(),
                    'date' => $date->toDateString(),
                    'status' => $status,
                    'revenue' => $status === 'accepted' ? 120 : 0,
                ]);
            }
        }
    }
}
