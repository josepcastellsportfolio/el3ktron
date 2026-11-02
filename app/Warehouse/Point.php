<?php

namespace App\Warehouse;

/** A position on the warehouse floor: aisle number, and bay along the aisle (0 = front cross-aisle). */
final readonly class Point
{
    public function __construct(public int $aisle, public int $bay) {}
}
