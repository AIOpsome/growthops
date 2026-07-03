<?php

namespace App\Services\CsvImports;

use App\Models\Campaign;
use App\Models\DailyMetric;
use App\Models\Lead;
use App\Services\CsvImports\Parsers\GoogleAdsCampaignParser;
use App\Services\CsvImports\Parsers\LeadsParser;
use App\Services\CsvImports\Parsers\MetaAdsCampaignParser;
use App\Services\CsvImports\Parsers\TaboolaCampaignParser;
use App\Services\CsvImports\Parsers\TikTokCampaignParser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CampaignCsvImporter
{
    /**
     * @return array{platform: string, campaigns: int, metrics: int}
     */
    public function import(string $path, ?string $platform = null): array
    {
        $document = $this->read($path, $platform);

        if ($document['platform'] === 'leads') {
            return $this->importLeads($document['rows']);
        }

        $rows = $this->parser($document['platform'])->parse($document['rows']);

        return DB::transaction(function () use ($document, $rows): array {
            $campaigns = [];
            $metrics = 0;

            foreach ($rows as $row) {
                if ($row['name'] === '' || $row['date'] === '') {
                    throw new CsvImportException('CSV contains a row without a campaign name or date.');
                }

                $campaign = Campaign::query()->updateOrCreate(
                    ['platform' => $document['platform'], 'name' => $row['name']],
                    ['external_id' => $row['external_id'] ?: null],
                );

                $metric = DailyMetric::query()->firstOrNew([
                    'campaign_id' => $campaign->id,
                    'date' => CarbonImmutable::parse($row['date'])->toDateString(),
                ]);

                $metric->fill([
                    'spend' => $row['spend'],
                    'impressions' => $row['impressions'],
                    'clicks' => $row['clicks'],
                    'conversions' => $row['conversions'],
                ]);

                if (! $metric->exists || (float) $row['revenue'] > 0) {
                    $metric->revenue = $row['revenue'];
                }

                $metric->save();

                $campaigns[$campaign->id] = true;
                $metrics++;
            }

            return [
                'platform' => $document['platform'],
                'campaigns' => count($campaigns),
                'metrics' => $metrics,
            ];
        });
    }

    /**
     * @return array{platform: string, rows: array<int, array<string, string>>}
     */
    private function read(string $path, ?string $platform): array
    {
        if (! is_readable($path)) {
            throw new CsvImportException('CSV file could not be read.');
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new CsvImportException('CSV file could not be opened.');
        }

        $lines = [];

        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $lines[] = array_map(fn (?string $cell): string => trim((string) $cell), $row);
        }

        fclose($handle);

        [$detectedPlatform, $headerIndex] = $this->findHeader($lines, $platform);
        $header = array_map(fn (string $cell): string => $this->stripBom($cell), $lines[$headerIndex]);
        $rows = [];

        foreach (array_slice($lines, $headerIndex + 1) as $line) {
            if ($this->blank($line) || count($line) < count($header)) {
                continue;
            }

            $mapped = array_combine($header, array_slice($line, 0, count($header)));

            if ($mapped === false) {
                continue;
            }

            $rows[] = $mapped;
        }

        if ($rows === []) {
            throw new CsvImportException('CSV contains no importable campaign rows.');
        }

        return ['platform' => $detectedPlatform, 'rows' => $rows];
    }

    /**
     * @param  array<int, array<int, string>>  $lines
     * @return array{0: string, 1: int}
     */
    private function findHeader(array $lines, ?string $platform): array
    {
        foreach ($lines as $index => $line) {
            $headers = array_map(fn (string $cell): string => $this->normalizeHeader($cell), $line);

            if (($platform === null || $platform === 'meta') && $this->contains($headers, ['campaign name', 'amount spent (usd)', 'impressions', 'link clicks', 'results', 'reporting starts'])) {
                return ['meta', $index];
            }

            if (($platform === null || $platform === 'google') && $this->contains($headers, ['campaign', 'day', 'cost', 'clicks', 'impr.', 'conversions'])) {
                return ['google', $index];
            }

            if (($platform === null || $platform === 'tiktok') && $this->containsAny($headers, [
                ['campaign name', 'campaign'],
                ['cost', 'spend', 'amount spent'],
                ['impressions'],
                ['clicks'],
                ['cpc (destination)', 'cpc destination', 'destination cpc'],
                ['conversions', 'actions'],
                ['day', 'date', 'reporting starts'],
            ])) {
                return ['tiktok', $index];
            }

            if (($platform === null || $platform === 'taboola') && $this->containsAny($headers, [
                ['campaign name', 'campaign'],
                ['spent', 'spend', 'cost'],
                ['clicks'],
                ['impressions'],
                ['actions', 'conversions'],
                ['day', 'date', 'reporting starts'],
            ])) {
                return ['taboola', $index];
            }

            if (($platform === null || $platform === 'leads') && $this->containsAny($headers, [
                ['lead id', 'id'],
                ['date'],
                ['campaign reference', 'campaign', 'campaign name'],
                ['status'],
                ['payout', 'revenue'],
            ])) {
                return ['leads', $index];
            }
        }

        $target = $platform === null ? 'campaign or leads' : $platform;

        throw new CsvImportException("Unrecognized {$target} CSV headers.");
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $required
     */
    private function contains(array $headers, array $required): bool
    {
        return collect($required)->every(fn (string $header): bool => in_array($header, $headers, true));
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $required
     */
    private function containsAny(array $headers, array $required): bool
    {
        return collect($required)->every(
            fn (array $group): bool => collect($group)->contains(
                fn (string $header): bool => in_array($header, $headers, true),
            ),
        );
    }

    private function normalizeHeader(string $header): string
    {
        return mb_strtolower($this->stripBom(trim($header)));
    }

    private function stripBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    /**
     * @param  array<int, string>  $line
     */
    private function blank(array $line): bool
    {
        return collect($line)->every(fn (string $cell): bool => trim($cell) === '');
    }

    /**
     * @return array{platform: string, campaigns: int, metrics: int}
     */
    private function importLeads(array $rows): array
    {
        $leads = (new LeadsParser)->parse($rows);

        return DB::transaction(function () use ($leads): array {
            $campaigns = [];
            $dailyKeys = [];

            foreach ($leads as $lead) {
                if ($lead['external_id'] === '' || $lead['date'] === '' || $lead['campaign_reference'] === '') {
                    throw new CsvImportException('Leads CSV contains a row without a lead id, campaign reference, or date.');
                }

                if (! in_array($lead['status'], ['accepted', 'rejected', 'pending'], true)) {
                    throw new CsvImportException('Leads CSV contains an unsupported lead status.');
                }

                $campaign = $this->campaignForLead($lead['campaign_reference']);
                $date = CarbonImmutable::parse($lead['date'])->toDateString();

                Lead::query()->updateOrCreate(
                    ['external_id' => $lead['external_id']],
                    [
                        'campaign_id' => $campaign->id,
                        'date' => $date,
                        'status' => $lead['status'],
                        'revenue' => $lead['revenue'],
                    ],
                );

                $campaigns[$campaign->id] = $campaign;
                $dailyKeys["{$campaign->id}:{$date}"] = [$campaign, $date];
            }

            foreach ($dailyKeys as [$campaign, $date]) {
                $this->syncDailyLeadRevenue($campaign, $date);
            }

            return [
                'platform' => 'leads',
                'campaigns' => count($campaigns),
                'metrics' => count($dailyKeys),
            ];
        });
    }

    private function campaignForLead(string $reference): Campaign
    {
        /** @var Collection<int, Campaign> $campaigns */
        $campaigns = Campaign::query()
            ->where('external_id', $reference)
            ->orWhere('name', $reference)
            ->get();

        if ($campaigns->count() === 1) {
            return $campaigns->firstOrFail();
        }

        $externalMatches = $campaigns->where('external_id', $reference);

        if ($externalMatches->count() === 1) {
            return $externalMatches->first();
        }

        if ($campaigns->isEmpty()) {
            throw new CsvImportException("Lead references unknown campaign [{$reference}].");
        }

        throw new CsvImportException("Lead references ambiguous campaign [{$reference}].");
    }

    private function syncDailyLeadRevenue(Campaign $campaign, string $date): void
    {
        $revenue = $campaign->leads()
            ->whereDate('date', $date)
            ->where('status', 'accepted')
            ->sum('revenue');

        $metric = DailyMetric::query()->firstOrNew([
            'campaign_id' => $campaign->id,
            'date' => $date,
        ]);

        $metric->revenue = $revenue;
        $metric->save();
    }

    private function parser(string $platform): MetaAdsCampaignParser|GoogleAdsCampaignParser|TaboolaCampaignParser|TikTokCampaignParser
    {
        return match ($platform) {
            'meta' => new MetaAdsCampaignParser,
            'google' => new GoogleAdsCampaignParser,
            'taboola' => new TaboolaCampaignParser,
            'tiktok' => new TikTokCampaignParser,
            default => throw new CsvImportException('Unsupported platform.'),
        };
    }
}
