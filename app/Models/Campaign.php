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
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
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
     * @param  Builder<Campaign>  $query
     * @return Builder<Campaign>
     */
    public function scopeWithLeadTotals(Builder $query): Builder
    {
        return $query->withCount([
            'leads as leads_total',
            'leads as accepted_leads_total' => fn (Builder $query): Builder => $query->where('status', 'accepted'),
            'leads as rejected_leads_total' => fn (Builder $query): Builder => $query->where('status', 'rejected'),
            'leads as pending_leads_total' => fn (Builder $query): Builder => $query->where('status', 'pending'),
        ]);
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

    protected function leadsTotal(): Attribute
    {
        return Attribute::get(fn (): int => $this->leadTotal('leads_total'));
    }

    protected function acceptedLeadsTotal(): Attribute
    {
        return Attribute::get(fn (): int => $this->leadTotal('accepted_leads_total', 'accepted'));
    }

    protected function rejectedLeadsTotal(): Attribute
    {
        return Attribute::get(fn (): int => $this->leadTotal('rejected_leads_total', 'rejected'));
    }

    protected function pendingLeadsTotal(): Attribute
    {
        return Attribute::get(fn (): int => $this->leadTotal('pending_leads_total', 'pending'));
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

    protected function cpl(): Attribute
    {
        return Attribute::get(fn (): ?float => $this->leads_total > 0 ? $this->spend_total / $this->leads_total : null);
    }

    protected function roas(): Attribute
    {
        return Attribute::get(fn (): ?float => $this->spend_total > 0 ? $this->revenue_total / $this->spend_total : null);
    }

    protected function epc(): Attribute
    {
        return Attribute::get(fn (): ?float => $this->clicks_total > 0 ? $this->revenue_total / $this->clicks_total : null);
    }

    protected function leadAcceptanceRate(): Attribute
    {
        return Attribute::get(function (): ?float {
            $resolvedLeads = $this->accepted_leads_total + $this->rejected_leads_total;

            return $resolvedLeads > 0 ? ($this->accepted_leads_total / $resolvedLeads) * 100 : null;
        });
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

    private function leadTotal(string $attribute, ?string $status = null): int
    {
        if (array_key_exists($attribute, $this->attributes)) {
            return (int) $this->attributes[$attribute];
        }

        if (! $this->exists) {
            return 0;
        }

        $query = $this->leads();

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->count();
    }
}
