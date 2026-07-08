<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

#[Fillable(['actor', 'workflow', 'intent', 'confirmed', 'details'])]
class GuideInvocation extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Guide invocation rows are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Guide invocation rows are immutable and cannot be deleted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmed' => 'boolean',
            'details' => 'array',
        ];
    }
}
