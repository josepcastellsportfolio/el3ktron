<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A catalogue reference. The ERP owns it; this table is a mirror kept up to
 * date by CatalogSync.
 *
 * @property ProductStatus $status
 * @property Carbon|null $synced_at
 * @property int|string|null $available free units, when the query adds withSum('stock as available')
 */
#[Fillable(['sku', 'ean', 'name', 'family_id', 'price_cents', 'status', 'erp_hash', 'synced_at'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'price_cents' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Family, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /** @return HasMany<Stock, $this> */
    public function stock(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function isActive(): bool
    {
        return $this->status === ProductStatus::Active;
    }
}
