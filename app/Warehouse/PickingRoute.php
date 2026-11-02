<?php

namespace App\Warehouse;

/**
 * Orders a picking list so the picker walks the warehouse once.
 *
 * S-shape (serpentine) routing: aisles in ascending order, up one aisle and
 * down the next. It is the standard manual-picking heuristic: simple enough
 * for a person to follow on paper, and far shorter than catalogue order.
 */
final class PickingRoute
{
    /**
     * @param  list<PickLine>  $lines
     * @return list<PickLine>
     */
    public static function serpentine(array $lines): array
    {
        $byAisle = [];
        foreach ($lines as $line) {
            $byAisle[$line->point->aisle][] = $line;
        }
        ksort($byAisle);

        $ordered = [];
        $upwards = true;
        foreach ($byAisle as $aisleLines) {
            usort($aisleLines, fn (PickLine $a, PickLine $b) => $upwards
                ? [$a->point->bay, $a->sku] <=> [$b->point->bay, $b->sku]
                : [$b->point->bay, $a->sku] <=> [$a->point->bay, $b->sku]);
            array_push($ordered, ...$aisleLines);
            $upwards = ! $upwards;
        }

        return $ordered;
    }

    /**
     * The baseline: the order a list comes out of the catalogue, by SKU.
     *
     * @param  list<PickLine>  $lines
     * @return list<PickLine>
     */
    public static function catalogueOrder(array $lines): array
    {
        usort($lines, fn (PickLine $a, PickLine $b) => [$a->sku, $a->locationCode] <=> [$b->sku, $b->locationCode]);

        return $lines;
    }

    /**
     * The fair baseline: sorted by location, every aisle walked from the
     * front. What anyone does with a list printed in location order.
     *
     * @param  list<PickLine>  $lines
     * @return list<PickLine>
     */
    public static function locationOrder(array $lines): array
    {
        usort($lines, fn (PickLine $a, PickLine $b) => [$a->point->aisle, $a->point->bay, $a->sku] <=> [$b->point->aisle, $b->point->bay, $b->sku]);

        return $lines;
    }

    /**
     * @param  list<PickLine>  $lines
     * @return list<Point>
     */
    public static function points(array $lines): array
    {
        return array_map(fn (PickLine $line) => $line->point, $lines);
    }
}
