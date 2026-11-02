<?php

namespace App\Models;

use App\Enums\InstallationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One planogram going to one store on a date.
 *
 * @property InstallationStatus $status
 * @property Carbon|null $scheduled_for
 */
#[Fillable(['store_id', 'planogram_id', 'status', 'scheduled_for', 'needs_review', 'review_reason', 'checked_at', 'reserved_at'])]
class Installation extends Model
{
    protected $attributes = ['status' => 'draft', 'needs_review' => false];

    protected function casts(): array
    {
        return [
            'status' => InstallationStatus::class,
            'scheduled_for' => 'date',
            'needs_review' => 'boolean',
            'checked_at' => 'datetime',
            'reserved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Planogram, $this> */
    public function planogram(): BelongsTo
    {
        return $this->belongsTo(Planogram::class);
    }

    /** @return HasMany<InstallationLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InstallationLine::class);
    }
}
