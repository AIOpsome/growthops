<?php

namespace App\Services;

use App\Models\RecommendedAction;
use Illuminate\Support\Facades\Http;
use Throwable;

class ActionNarrator
{
    /**
     * @return array{narrative: string, reasoning: ?string}
     */
    public function narrate(RecommendedAction $action): array
    {
        $baseUrl = config('growthops.llm.base_url');
        $apiKey = config('growthops.llm.api_key');

        if (blank($baseUrl) || blank($apiKey)) {
            return ['narrative' => $this->template($action), 'reasoning' => null];
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(config('growthops.llm.timeout'))
                ->post(rtrim($baseUrl, '/').'/chat/completions', [
                    'model' => config('growthops.llm.model'),
                    'messages' => [
                        ['role' => 'user', 'content' => $this->prompt($action)],
                    ],
                ]);

            if ($response->successful() && filled($response->json('choices.0.message.content'))) {
                return [
                    'narrative' => trim($response->json('choices.0.message.content')),
                    'reasoning' => filled($response->json('choices.0.message.reasoning_content'))
                        ? trim($response->json('choices.0.message.reasoning_content'))
                        : null,
                ];
            }
        } catch (Throwable) {
            // fall through to template narrative
        }

        return ['narrative' => $this->template($action), 'reasoning' => null];
    }

    private function prompt(RecommendedAction $action): string
    {
        $evidence = json_encode($action->evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
            You are a media-buyer assistant writing a short recommendation summary for a paid-ads action queue.

            Action type: {$action->type}
            Confidence: {$action->confidence}
            Risk: {$action->risk}
            Expected upside: \${$action->expected_upside}
            Evidence (JSON): {$evidence}

            Write 2-3 sentences in plain media-buyer language explaining why this action is recommended,
            referencing concrete numbers from the evidence above. Then state one key risk and one thing
            to verify before approving. Do not use markdown formatting.
            PROMPT;
    }

    private function template(RecommendedAction $action): string
    {
        $evidenceSummary = collect($action->evidence)
            ->map(fn (mixed $value, string $key): string => "{$key}: ".(is_scalar($value) ? $value : json_encode($value)))
            ->implode(', ');

        return "This {$action->type} recommendation (confidence {$action->confidence}) is based on {$evidenceSummary}. ".
            "Expected upside: \${$action->expected_upside}. Risk level: {$action->risk} — verify the underlying metrics before approving.";
    }
}
