<?php

namespace App\Models;

use App\Warehouse\Point;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** A pick face: aisle, bay along the aisle, shelf level. */
#[Fillable(['code', 'aisle', 'bay', 'level'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    public function point(): Point
    {
        return new Point($this->aisle, $this->bay);
    }
}
