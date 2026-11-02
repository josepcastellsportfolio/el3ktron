<?php

namespace App\Warehouse;

/** One stop on a picking list. */
final readonly class PickLine
{
    public function __construct(
        public string $sku,
        public string $name,
        public string $locationCode,
        public Point $point,
        public int $quantity,
    ) {}
}
