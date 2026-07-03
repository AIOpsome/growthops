<?php

namespace App\Models;

use App\Services\ActionNarrator;
use Database\Factories\RecommendedActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['campaign_id', 'run_date', 'type', 'evidence', 'confidence', 'risk', 'expected_upside', 'status', 'applied_parameter', 'narrative'])]
class RecommendedAction extends Model
{
    /** @use HasFactory<RecommendedActionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'evidence' => 'array',
            'confidence' => 'decimal:2',
            'expected_upside' => 'decimal:2',
            'applied_parameter' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function ensureNarrative(): string
    {
        if (filled($this->narrative)) {
            return $this->narrative;
        }

        $this->narrative = app(ActionNarrator::class)->narrate($this);
        $this->saveQuietly();

        return $this->narrative;
    }

    /**
     * @return HasMany<ActionAudit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(ActionAudit::class)->latest('id');
    }

    /**
     * @return HasOne<ExecutionLog, $this>
     */
    public function executionLog(): HasOne
    {
        return $this->hasOne(ExecutionLog::class)->latestOfMany();
    }
}
