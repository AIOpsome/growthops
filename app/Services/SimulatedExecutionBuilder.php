<?php

namespace App\Services;

use App\Models\RecommendedAction;
use InvalidArgumentException;

final class SimulatedExecutionBuilder
{
    /**
     * The numeric parameter the operator can edit before approving. Defaults
     * come from config, except `fix` which prefers the campaign's target CPA.
     */
    public function defaultParameter(RecommendedAction $action): float
    {
        $config = $this->parameterConfig($action->type);

        if ($action->type === 'fix' && $action->campaign->target_cpa !== null) {
            return (float) $action->campaign->target_cpa;
        }

        return (float) $config['default'];
    }

    public function parameterKey(string $type): string
    {
        return $this->parameterConfig($type)['key'];
    }

    /**
     * Build the platform API call that WOULD be sent. Never dispatched.
     *
     * @return array{platform: string, simulated_endpoint: string, simulated_payload: array<string, mixed>}
     */
    public function build(RecommendedAction $action, float $parameter): array
    {
        $platform = $action->campaign->platform;
        $ref = (string) ($action->campaign->external_id ?: $action->campaign_id);

        [$endpoint, $payload] = match ($platform) {
            'meta' => $this->meta($action->type, $ref, $parameter, $action),
            'google' => $this->google($action->type, $ref, $parameter, $action),
            'taboola' => $this->taboola($action->type, $ref, $parameter, $action),
            'tiktok' => $this->tiktok($action->type, $ref, $parameter, $action),
            default => throw new InvalidArgumentException("Unsupported platform [{$platform}]."),
        };

        return [
            'platform' => $platform,
            'simulated_endpoint' => $endpoint,
            'simulated_payload' => $payload,
        ];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function meta(string $type, string $ref, float $parameter, RecommendedAction $action): array
    {
        $version = config('growthops.meta.graph_version');

        return match ($type) {
            'pause' => ["POST /{$version}/{$ref}", $this->isFullPause($parameter)
                ? ['status' => 'PAUSED']
                : ['daily_budget' => $this->toCents($parameter)]],
            'scale' => ["POST /{$version}/{$ref}", ['daily_budget' => $this->scaledBudgetCents($action, $parameter)]],
            'fix' => ["POST /{$version}/{$ref}", ['bid_strategy' => 'COST_CAP', 'bid_amount' => $this->toCents($parameter)]],
            'investigate' => ["GET /{$version}/{$ref}/insights", [
                'fields' => ['spend', 'actions', 'cost_per_action_type'],
                'time_range' => $this->lookbackRange($action, $parameter, 'since', 'until'),
            ]],
            default => throw new InvalidArgumentException("Unsupported action type [{$type}]."),
        };
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function google(string $type, string $ref, float $parameter, RecommendedAction $action): array
    {
        $campaign = "customers/{customer-id}/campaigns/{$ref}";

        return match ($type) {
            'pause' => $this->isFullPause($parameter)
                ? ['CampaignService.MutateCampaigns', ['operations' => [['update' => ['resourceName' => $campaign, 'status' => 'PAUSED'], 'updateMask' => 'status']]]]
                : ['CampaignBudgetService.MutateCampaignBudgets', ['operations' => [['update' => ['resourceName' => "customers/{customer-id}/campaignBudgets/{$ref}", 'amountMicros' => $this->toMicros($parameter)], 'updateMask' => 'amount_micros']]]],
            'scale' => ['CampaignBudgetService.MutateCampaignBudgets', ['operations' => [['update' => ['resourceName' => "customers/{customer-id}/campaignBudgets/{$ref}", 'amountMicros' => $this->toMicros($this->scaledBudget($action, $parameter))], 'updateMask' => 'amount_micros']]]],
            'fix' => ['CampaignService.MutateCampaigns', ['operations' => [['update' => ['resourceName' => $campaign, 'targetCpa' => ['targetCpaMicros' => $this->toMicros($parameter)]], 'updateMask' => 'target_cpa.target_cpa_micros']]]],
            'investigate' => (function () use ($ref, $parameter, $action): array {
                $range = $this->lookbackRange($action, $parameter);

                return ['GoogleAdsService.SearchStream', ['query' => "SELECT campaign.id, metrics.cost_micros, metrics.conversions FROM campaign WHERE campaign.id = {$ref} AND segments.date BETWEEN '{$range['since']}' AND '{$range['until']}'"]];
            })(),
            default => throw new InvalidArgumentException("Unsupported action type [{$type}]."),
        };
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function taboola(string $type, string $ref, float $parameter, RecommendedAction $action): array
    {
        $base = "POST /backstage/api/1.0/{account-id}/campaigns/{$ref}";

        return match ($type) {
            'pause' => $this->isFullPause($parameter)
                ? [$base, ['is_active' => false]]
                : [$base, ['daily_cap' => round($parameter, 2)]],
            'scale' => [$base, ['daily_cap' => round($this->scaledBudget($action, $parameter), 2)]],
            'fix' => [$base, ['cpa_goal' => round($parameter, 2)]],
            'investigate' => ["GET /backstage/api/1.0/{account-id}/campaigns/{$ref}/performance", [
                'dimension' => 'day',
                ...$this->lookbackRange($action, $parameter, 'start_date', 'end_date'),
            ]],
            default => throw new InvalidArgumentException("Unsupported action type [{$type}]."),
        };
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function tiktok(string $type, string $ref, float $parameter, RecommendedAction $action): array
    {
        return match ($type) {
            'pause' => $this->isFullPause($parameter)
                ? ['POST /open_api/v1.3/campaign/status/update/', ['campaign_ids' => [$ref], 'operation_status' => 'DISABLE']]
                : ['POST /open_api/v1.3/campaign/update/', ['campaign_id' => $ref, 'budget' => round($parameter, 2)]],
            'scale' => ['POST /open_api/v1.3/campaign/update/', ['campaign_id' => $ref, 'budget' => round($this->scaledBudget($action, $parameter), 2)]],
            'fix' => ['POST /open_api/v1.3/campaign/update/', ['campaign_id' => $ref, 'deep_bid_type' => 'MIN', 'conversion_bid_price' => round($parameter, 2)]],
            'investigate' => ['GET /open_api/v1.3/report/integrated/get/', [
                'dimensions' => ['campaign_id'],
                'metrics' => ['spend', 'conversion', 'cost_per_conversion'],
                'campaign_ids' => [$ref],
                ...$this->lookbackRange($action, $parameter, 'start_date', 'end_date'),
            ]],
            default => throw new InvalidArgumentException("Unsupported action type [{$type}]."),
        };
    }

    private function scaledBudget(RecommendedAction $action, float $pct): float
    {
        $current = (float) ($action->campaign->daily_budget ?? config('growthops.approval.default_daily_budget'));

        return $current * (1 + $pct / 100);
    }

    private function scaledBudgetCents(RecommendedAction $action, float $pct): int
    {
        return $this->toCents($this->scaledBudget($action, $pct));
    }

    private function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function toMicros(float $amount): int
    {
        return (int) round($amount * 1_000_000);
    }

    private function isFullPause(float $budgetCap): bool
    {
        return $budgetCap <= 0.0;
    }

    /**
     * @return array<string, string>
     */
    private function lookbackRange(RecommendedAction $action, float $lookbackDays, string $sinceKey = 'since', string $untilKey = 'until'): array
    {
        $days = max(1, (int) round($lookbackDays));
        $until = $action->run_date->copy();
        $since = $until->copy()->subDays($days - 1);

        return [
            $sinceKey => $since->toDateString(),
            $untilKey => $until->toDateString(),
        ];
    }

    /**
     * @return array{key: string, label: string, default: float}
     */
    private function parameterConfig(string $type): array
    {
        $config = config("growthops.approval.parameters.{$type}");

        if ($config === null) {
            throw new InvalidArgumentException("No approval parameter configured for action type [{$type}].");
        }

        return $config;
    }
}
