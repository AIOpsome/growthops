<?php

namespace App\Services\CsvImports\Parsers;

class MetaAdsCampaignParser
{
    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function parse(array $rows): array
    {
        return array_map(fn (array $row): array => [
            'platform' => 'meta',
            'external_id' => $this->value($row, ['Campaign ID', 'Campaign Id']),
            'name' => $this->value($row, ['Campaign name']),
            'date' => $this->value($row, ['Reporting starts']),
            'spend' => $this->number($this->value($row, ['Amount spent (USD)'])),
            'impressions' => (int) $this->number($this->value($row, ['Impressions'])),
            'clicks' => (int) $this->number($this->value($row, ['Link clicks'])),
            'conversions' => $this->number($this->value($row, ['Results'])),
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
