<?php

namespace App\Services\CsvImports;

use App\Models\Campaign;
use App\Models\DailyMetric;
use App\Services\CsvImports\Parsers\GoogleAdsCampaignParser;
use App\Services\CsvImports\Parsers\MetaAdsCampaignParser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CampaignCsvImporter
{
    /**
     * @return array{platform: string, campaigns: int, metrics: int}
     */
    public function import(string $path, ?string $platform = null): array
    {
        $document = $this->read($path, $platform);
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

                DailyMetric::query()->updateOrCreate(
                    [
                        'campaign_id' => $campaign->id,
                        'date' => CarbonImmutable::parse($row['date'])->toDateString(),
                    ],
                    [
                        'spend' => $row['spend'],
                        'impressions' => $row['impressions'],
                        'clicks' => $row['clicks'],
                        'conversions' => $row['conversions'],
                        'revenue' => $row['revenue'],
                    ],
                );

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

        while (($row = fgetcsv($handle)) !== false) {
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
        }

        $target = $platform === null ? 'Meta Ads or Google Ads' : $platform;

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

    private function parser(string $platform): MetaAdsCampaignParser|GoogleAdsCampaignParser
    {
        return match ($platform) {
            'meta' => new MetaAdsCampaignParser,
            'google' => new GoogleAdsCampaignParser,
            default => throw new CsvImportException('Unsupported platform.'),
        };
    }
}
