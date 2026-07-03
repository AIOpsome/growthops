<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

#[Fillable(['recommended_action_id', 'actor', 'from_status', 'to_status', 'reason', 'edited_value'])]
class ActionAudit extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Action audit rows are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Action audit rows are immutable and cannot be deleted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'edited_value' => 'array',
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
