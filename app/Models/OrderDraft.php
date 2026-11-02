<?php

namespace App\Models;

use App\Enums\OrderDraftStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An order read from an email or PDF by an extractor. It is only a proposal:
 * nothing leaves this table until a person approves it.
 *
 * @property OrderDraftStatus $status
 * @property Carbon|null $reviewed_at
 * @property list<array{raw: string, sku: string|null, quantity: int, product_id: int|null}> $lines
 */
#[Fillable(['store_id', 'extractor', 'status', 'source_text', 'lines', 'reviewed_by', 'reviewed_at'])]
class OrderDraft extends Model
{
    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'status' => OrderDraftStatus::class,
            'lines' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
