<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\DailyMetric;
use App\Models\GuideInvocation;
use App\Models\Lead;
use App\Models\RecommendedAction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class OperatorGuideService
{
    /**
     * Explicit allowlist of everything the operator guide is permitted to do.
     * Read workflows only query existing GrowthOps data; the single form_assist
     * workflow drafts a campaign brief and never submits without confirmation.
     * Nothing here changes campaign spend, pauses/scales, or writes to an ad
     * account.
     *
     * @var array<string, array{label: string, mode: 'read'|'form_assist', keywords: list<string>}>
     */
    private const WORKFLOWS = [
        'find_stuck_leads' => [
            'label' => 'Find stuck leads',
            'mode' => 'read',
            'keywords' => ['stuck', 'lead', 'leads', 'pending'],
        ],
        'show_risky_campaigns' => [
            'label' => 'Show risky campaigns',
            'mode' => 'read',
            'keywords' => ['risk', 'risky', 'danger'],
        ],
        'prepare_weekly_report' => [
            'label' => 'Prepare weekly report',
            'mode' => 'read',
            'keywords' => ['report', 'weekly', 'summary', 'recap'],
        ],
        'fill_campaign_brief' => [
            'label' => 'Fill campaign brief',
            'mode' => 'form_assist',
            'keywords' => ['brief', 'fill', 'draft', 'new campaign'],
        ],
    ];

    /**
     * @return array<string, array{label: string, mode: 'read'|'form_assist', keywords: list<string>}>
     */
    public function allowedWorkflows(): array
    {
        return self::WORKFLOWS;
    }

    public function isAllowed(string $workflow): bool
    {
        return array_key_exists($workflow, self::WORKFLOWS);
    }

    public function mode(string $workflow): string
    {
        $this->assertAllowed($workflow);

        return self::WORKFLOWS[$workflow]['mode'];
    }

    /**
     * Map a free-text operator question onto exactly one allowlisted workflow.
     * Returns null when nothing in the allowlist matches, so unrecognized
     * requests never fall through to an unbounded action. Matching is
     * word-boundary aware so substrings never misroute (e.g. "fulfill" must
     * not trigger the "fill" keyword).
     */
    public function resolveIntent(string $question): ?string
    {
        $normalized = Str::lower($question);

        foreach (self::WORKFLOWS as $workflow => $definition) {
            foreach ($definition['keywords'] as $keyword) {
                if (preg_match('/\b'.preg_quote($keyword, '/').'\b/', $normalized) === 1) {
                    return $workflow;
                }
            }
        }

        return null;
    }

    /**
     * Run a read-only analysis workflow and attach a narrative summary.
     *
     * @return array{workflow: string, summary: string, data: array<string, mixed>}
     */
    public function runReadWorkflow(string $workflow): array
    {
        $this->assertAllowed($workflow);

        if (self::WORKFLOWS[$workflow]['mode'] !== 'read') {
            throw new InvalidArgumentException("Workflow [{$workflow}] is not a read-only workflow.");
        }

        $data = match ($workflow) {
            'find_stuck_leads' => $this->findStuckLeads(),
            'show_risky_campaigns' => $this->showRiskyCampaigns(),
            'prepare_weekly_report' => $this->prepareWeeklyReport(),
            default => throw new InvalidArgumentException("Unknown read workflow [{$workflow}]."),
        };

        return [
            'workflow' => $workflow,
            'summary' => $this->summarize($workflow, $data),
            'data' => $data,
        ];
    }

    /**
     * Log guide usage. The raw operator question is never stored verbatim — we
     * persist the resolved workflow key plus an email-redacted, 120-char
     * truncated intent. Email redaction is best-effort only (it does not strip
     * names, phone numbers, or account IDs), so callers must never pass lead
     * records or other sensitive free text as $details.
     *
     * @param  array<string, mixed>|null  $details
     */
    public function logInvocation(
        string $actor,
        string $workflow,
        ?string $rawIntent = null,
        bool $confirmed = false,
        ?array $details = null,
    ): GuideInvocation {
        return GuideInvocation::create([
            'actor' => $actor,
            'workflow' => $workflow,
            'intent' => $rawIntent !== null ? $this->normalizeIntent($rawIntent) : null,
            'confirmed' => $confirmed,
            'details' => $details,
        ]);
    }

    /**
     * @return array{count: int, by_campaign: list<array{campaign: string, stuck: int}>}
     */
    private function findStuckLeads(): array
    {
        $campaigns = Campaign::query()
            ->withCount(['leads as stuck_leads_total' => fn ($query) => $query->where('status', 'pending')])
            ->get()
            ->filter(fn (Campaign $campaign): bool => (int) $campaign->stuck_leads_total > 0)
            ->sortByDesc('stuck_leads_total')
            ->values();

        return [
            'count' => (int) $campaigns->sum('stuck_leads_total'),
            'by_campaign' => $campaigns
                ->map(fn (Campaign $campaign): array => [
                    'campaign' => $campaign->name,
                    'stuck' => (int) $campaign->stuck_leads_total,
                ])
                ->all(),
        ];
    }

    /**
     * Reuses the detector-defined risk already stored on pending recommended
     * actions rather than inventing a second definition of "risky".
     *
     * @return array{count: int, campaigns: list<array{campaign: string, platform: string, risk: string, type: string}>}
     */
    private function showRiskyCampaigns(): array
    {
        $actions = RecommendedAction::query()
            ->with('campaign')
            ->where('status', 'pending')
            ->whereIn('risk', ['high', 'medium'])
            ->get();

        $campaigns = $actions
            ->sortByDesc(fn (RecommendedAction $action): int => $action->risk === 'high' ? 1 : 0)
            ->unique('campaign_id')
            ->map(fn (RecommendedAction $action): array => [
                'campaign' => $action->campaign->name,
                'platform' => $action->campaign->platform,
                'risk' => $action->risk,
                'type' => $action->type,
            ])
            ->values();

        return [
            'count' => $campaigns->count(),
            'campaigns' => $campaigns->all(),
        ];
    }

    /**
     * @return array{window_days: int, spend: float, conversions: float, revenue: float, roas: ?float, pending_actions: int, stuck_leads: int}
     */
    private function prepareWeeklyReport(): array
    {
        $since = now()->subDays(7)->toDateString();

        $spend = (float) DailyMetric::query()->where('date', '>=', $since)->sum('spend');
        $conversions = (float) DailyMetric::query()->where('date', '>=', $since)->sum('conversions');
        $revenue = (float) DailyMetric::query()->where('date', '>=', $since)->sum('revenue');

        return [
            'window_days' => 7,
            'spend' => round($spend, 2),
            'conversions' => round($conversions, 2),
            'revenue' => round($revenue, 2),
            'roas' => $spend > 0 ? round($revenue / $spend, 2) : null,
            'pending_actions' => RecommendedAction::query()->where('status', 'pending')->count(),
            'stuck_leads' => Lead::query()->where('status', 'pending')->count(),
        ];
    }

    /**
     * Narrate the workflow result. Mirrors ActionNarrator: reuse the shared
     * growthops.llm gateway when configured, otherwise fall back to a
     * deterministic template so the guide never renders a broken answer.
     *
     * @param  array<string, mixed>  $data
     */
    private function summarize(string $workflow, array $data): string
    {
        $baseUrl = config('growthops.llm.base_url');
        $apiKey = config('growthops.llm.api_key');

        if (blank($baseUrl) || blank($apiKey)) {
            return $this->template($workflow, $data);
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(config('growthops.llm.timeout'))
                ->post(rtrim($baseUrl, '/').'/chat/completions', [
                    'model' => config('growthops.llm.model'),
                    'messages' => [
                        ['role' => 'user', 'content' => $this->prompt($workflow, $data)],
                    ],
                ]);

            if ($response->successful() && filled($response->json('choices.0.message.content'))) {
                return trim($response->json('choices.0.message.content'));
            }
        } catch (Throwable) {
            // fall through to deterministic template summary
        }

        return $this->template($workflow, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function prompt(string $workflow, array $data): string
    {
        $label = self::WORKFLOWS[$workflow]['label'];
        $payload = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
            You are a campaign-operations assistant summarizing the result of a read-only GrowthOps workflow for an operator.

            Workflow: {$label}
            Result (JSON): {$payload}

            Write 1-2 plain-language sentences telling the operator what the data shows and what to look at next.
            Reference concrete numbers from the JSON. Do not invent data. Do not use markdown formatting.
            PROMPT;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function template(string $workflow, array $data): string
    {
        return match ($workflow) {
            'find_stuck_leads' => $data['count'] === 0
                ? 'No stuck (pending) leads right now — every lead has been accepted or rejected.'
                : "{$data['count']} stuck (pending) leads across ".count($data['by_campaign']).' campaign(s). Review the highest-volume campaign first.',
            'show_risky_campaigns' => $data['count'] === 0
                ? 'No medium- or high-risk pending actions right now.'
                : "{$data['count']} campaign(s) have medium/high-risk pending actions. Open the Action Queue to review them before they affect spend.",
            'prepare_weekly_report' => sprintf(
                'Last 7 days: $%s spend, %s conversions, $%s revenue (ROAS %s). %d pending action(s) and %d stuck lead(s) still need attention.',
                number_format((float) $data['spend'], 2),
                (float) $data['conversions'],
                number_format((float) $data['revenue'], 2),
                $data['roas'] !== null ? number_format((float) $data['roas'], 2) : 'n/a',
                $data['pending_actions'],
                $data['stuck_leads'],
            ),
            default => 'Workflow complete.',
        };
    }

    /**
     * Best-effort scrub of the operator's question before it enters the audit
     * trail: strips email addresses and truncates to 120 chars. This is not a
     * full PII redactor — names, phone numbers, and account IDs survive — so it
     * relies on intents being operator-typed workflow requests, not lead data.
     */
    private function normalizeIntent(string $rawIntent): string
    {
        $redacted = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/', '[redacted-email]', $rawIntent) ?? '';

        return Str::limit(trim($redacted), 120);
    }

    private function assertAllowed(string $workflow): void
    {
        if (! $this->isAllowed($workflow)) {
            throw new InvalidArgumentException("Workflow [{$workflow}] is not on the operator-guide allowlist.");
        }
    }
}
