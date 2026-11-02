<?php

namespace App\Warehouse;

/**
 * A rectangular warehouse: parallel aisles joined by a front and a back
 * cross-aisle. The dock sits on the front cross-aisle, at aisle 0.
 *
 * Distance is the shortest walk between two pick faces: along the same aisle,
 * or out through whichever cross-aisle is shorter and into the other aisle.
 * Both picking orders are measured with this same function, so the
 * comparison is fair whatever its simplifications.
 */
final readonly class WarehouseLayout
{
    public function __construct(
        public int $baysPerAisle,
        public float $aisleSpacingMeters = 3.0,
        public float $bayWidthMeters = 1.4,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (int) config('el3ktron.warehouse.bays_per_aisle'),
            (float) config('el3ktron.warehouse.aisle_spacing_m'),
            (float) config('el3ktron.warehouse.bay_width_m'),
        );
    }

    public function dock(): Point
    {
        return new Point(0, 0);
    }

    public function distance(Point $from, Point $to): float
    {
        if ($from->aisle === $to->aisle) {
            return abs($from->bay - $to->bay) * $this->bayWidthMeters;
        }

        $back = $this->baysPerAisle + 1;
        $viaFront = $from->bay + $to->bay;
        $viaBack = ($back - $from->bay) + ($back - $to->bay);

        return min($viaFront, $viaBack) * $this->bayWidthMeters
            + abs($from->aisle - $to->aisle) * $this->aisleSpacingMeters;
    }

    /**
     * Meters walked from the dock through every point in order and back.
     *
     * @param  list<Point>  $points
     */
    public function routeLength(array $points): float
    {
        $total = 0.0;
        $at = $this->dock();
        foreach ($points as $point) {
            $total += $this->distance($at, $point);
            $at = $point;
        }

        return $total + $this->distance($at, $this->dock());
    }
}
