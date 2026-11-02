<?php

namespace App\Models;

use App\Enums\AlertSeverity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property AlertSeverity $severity
 * @property array<string, mixed>|null $context
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'severity', 'dedupe_key', 'message', 'context', 'resolved_at'])]
class Alert extends Model
{
    protected function casts(): array
    {
        return [
            'severity' => AlertSeverity::class,
            'context' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /** @param Builder<Alert> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }
}
