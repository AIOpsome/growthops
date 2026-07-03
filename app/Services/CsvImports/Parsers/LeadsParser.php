<?php

namespace App\Services\CsvImports\Parsers;

class LeadsParser
{
    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function parse(array $rows): array
    {
        return array_map(fn (array $row): array => [
            'external_id' => $this->value($row, ['lead id', 'Lead ID', 'Lead Id', 'id', 'ID']),
            'date' => $this->value($row, ['date', 'Date']),
            'campaign_reference' => $this->value($row, ['campaign reference', 'Campaign reference', 'Campaign Reference', 'campaign', 'Campaign', 'campaign name', 'Campaign name']),
            'status' => mb_strtolower($this->value($row, ['status', 'Status'])),
            'revenue' => $this->number($this->value($row, ['payout', 'Payout', 'revenue', 'Revenue'])),
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
