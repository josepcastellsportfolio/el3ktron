<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Units of one product at one location; `reserved` are held for installations. */
#[Fillable(['product_id', 'location_id', 'quantity', 'reserved'])]
class Stock extends Model
{
    protected $table = 'stock';

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'reserved' => 'integer'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function available(): int
    {
        return max(0, $this->quantity - $this->reserved);
    }
}
