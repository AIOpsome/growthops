<?php

namespace App\Models;

use Database\Factories\RecommendedActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['campaign_id', 'run_date', 'type', 'evidence', 'confidence', 'risk', 'expected_upside', 'status', 'narrative'])]
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
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
