<?php

namespace App\Models;

use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['platform', 'external_id', 'name', 'status', 'daily_budget', 'target_cpa', 'target_roas'])]
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    /**
     * @return HasMany<DailyMetric, $this>
     */
    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(DailyMetric::class);
    }

    /**
     * @return HasMany<RecommendedAction, $this>
     */
    public function recommendedActions(): HasMany
    {
        return $this->hasMany(RecommendedAction::class);
    }

    /**
     * @param  Builder<Campaign>  $query
     * @return Builder<Campaign>
     */
    public function scopeWithMetricTotals(Builder $query): Builder
    {
        return $query
            ->withSum('dailyMetrics as spend_total', 'spend')
            ->withSum('dailyMetrics as impressions_total', 'impressions')
            ->withSum('dailyMetrics as clicks_total', 'clicks')
            ->withSum('dailyMetrics as conversions_total', 'conversions')
            ->withSum('dailyMetrics as revenue_total', 'revenue');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daily_budget' => 'decimal:2',
            'target_cpa' => 'decimal:2',
            'target_roas' => 'decimal:2',
        ];
    }

    protected function spendTotal(): Attribute
    {
        return Attribute::get(fn (): float => $this->metricTotal('spend_total', 'spend'));
    }

    protected function impressionsTotal(): Attribute
    {
        return Attribute::get(fn (): int => (int) $this->metricTotal('impressions_total', 'impressions'));
    }

    protected function clicksTotal(): Attribute
    {
        return Attribute::get(fn (): int => (int) $this->metricTotal('clicks_total', 'clicks'));
    }

    protected function conversionsTotal(): Attribute
    {
        return Attribute::get(fn (): float => $this->metricTotal('conversions_total', 'conversions'));
    }

    protected function revenueTotal(): Attribute
    {
        return Attribute::get(fn (): float => $this->metricTotal('revenue_total', 'revenue'));
    }

    protected function cpc(): Attribute
    {
        return Attribute::get(fn (): ?float => $this->clicks_total > 0 ? $this->spend_total / $this->clicks_total : null);
    }

    protected function cpm(): Attribute
    {
        return Attribute::get(fn (): ?float => $this->impressions_total > 0 ? ($this->spend_total / $this->impressions_total) * 1000 : null);
    }

    protected function cpa(): Attribute
    {
        return Attribute::get(fn (): ?float => $this->conversions_total > 0 ? $this->spend_total / $this->conversions_total : null);
    }

    private function metricTotal(string $attribute, string $column): float
    {
        if (array_key_exists($attribute, $this->attributes)) {
            return (float) $this->attributes[$attribute];
        }

        if (! $this->exists) {
            return 0.0;
        }

        return (float) $this->dailyMetrics()->sum($column);
    }
}
