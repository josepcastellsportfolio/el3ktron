<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The assortment of a display or shelf run: which products, how many units. */
#[Fillable(['code', 'name'])]
class Planogram extends Model
{
    /** @return HasMany<PlanogramItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PlanogramItem::class);
    }
}
