<?php

namespace App\Services\CsvImports\Parsers;

class GoogleAdsCampaignParser
{
    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function parse(array $rows): array
    {
        return array_map(fn (array $row): array => [
            'platform' => 'google',
            'external_id' => $this->value($row, ['Campaign ID', 'Campaign ID ']),
            'name' => $this->value($row, ['Campaign']),
            'date' => $this->value($row, ['Day']),
            'spend' => $this->number($this->value($row, ['Cost'])),
            'impressions' => (int) $this->number($this->value($row, ['Impr.', 'Impressions'])),
            'clicks' => (int) $this->number($this->value($row, ['Clicks'])),
            'conversions' => $this->number($this->value($row, ['Conversions'])),
            'revenue' => 0.0,
        ], $rows);
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<int, string>  $headers
     */
    private function value(array $row, array $headers): string
    {
        foreach ($headers as $header) {
            if (array_key_exists($header, $row)) {
                return trim($row[$header]);
            }
        }

        return '';
    }

    private function number(string $value): float
    {
        $normalized = str_replace([',', '$', 'USD', '--'], '', trim($value));

        if ($normalized === '' || $normalized === '-') {
            return 0.0;
        }

        return (float) $normalized;
    }
}
