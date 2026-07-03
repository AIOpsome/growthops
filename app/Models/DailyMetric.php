<?php

namespace App\Models;

use Database\Factories\DailyMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['campaign_id', 'date', 'spend', 'impressions', 'clicks', 'conversions', 'revenue'])]
class DailyMetric extends Model
{
    /** @use HasFactory<DailyMetricFactory> */
    use HasFactory;

    protected $dateFormat = 'Y-m-d';

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'spend' => 'decimal:2',
            'conversions' => 'decimal:2',
            'revenue' => 'decimal:2',
        ];
    }
}
