<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['recommended_action_id', 'status', 'platform', 'simulated_endpoint', 'simulated_payload'])]
class ExecutionLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'simulated_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<RecommendedAction, $this>
     */
    public function recommendedAction(): BelongsTo
    {
        return $this->belongsTo(RecommendedAction::class);
    }
}
