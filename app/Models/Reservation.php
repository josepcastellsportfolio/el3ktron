<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['installation_line_id', 'stock_id', 'quantity'])]
class Reservation extends Model
{
    /** @return BelongsTo<Stock, $this> */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    /** @return BelongsTo<InstallationLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(InstallationLine::class, 'installation_line_id');
    }
}
