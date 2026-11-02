<?php

use App\Warehouse\PickingRoute;
use App\Warehouse\PickLine;
use App\Warehouse\Point;
use App\Warehouse\WarehouseLayout;

$layout = new WarehouseLayout(baysPerAisle: 20, aisleSpacingMeters: 3.0, bayWidthMeters: 1.0);

it('walks straight along the same aisle', function () use ($layout) {
    expect($layout->distance(new Point(2, 3), new Point(2, 10)))->toBe(7.0);
});

it('changes aisle through the shorter cross-aisle', function () use ($layout) {
    // Near the front: out the front (2 + 3 bays) and across one aisle.
    expect($layout->distance(new Point(1, 2), new Point(2, 3)))->toBe(5.0 + 3.0);
    // Near the back (bay 21 is the back cross-aisle): 2 + 1 bays out the back.
    expect($layout->distance(new Point(1, 19), new Point(2, 20)))->toBe(3.0 + 3.0);
});

it('measures a route from the dock and back', function () use ($layout) {
    // Dock (0,0) → (1,5): 5 bays + 1 aisle; and the same way back.
    expect($layout->routeLength([new Point(1, 5)]))->toBe(16.0);
});

it('orders picks in an S: up odd aisles, down even ones', function () {
    $line = fn (string $sku, int $aisle, int $bay) => new PickLine($sku, $sku, "$aisle-$bay", new Point($aisle, $bay), 1);
    $route = PickingRoute::serpentine([$line('A', 2, 3), $line('B', 1, 9), $line('C', 1, 2), $line('D', 2, 15)]);

    expect(array_map(fn (PickLine $l) => $l->sku, $route))->toBe(['C', 'B', 'D', 'A']);
});

it('walks less than catalogue order on a scattered list', function () use ($layout) {
    $lines = [];
    foreach ([[1, 18], [6, 2], [1, 3], [6, 17], [3, 10], [2, 5], [5, 12], [4, 1]] as $i => [$aisle, $bay]) {
        $lines[] = new PickLine(sprintf('E3-%05d', $i), 'x', "$aisle-$bay", new Point($aisle, $bay), 1);
    }

    $route = $layout->routeLength(PickingRoute::points(PickingRoute::serpentine($lines)));
    $catalogue = $layout->routeLength(PickingRoute::points(PickingRoute::catalogueOrder($lines)));

    expect($route)->toBeLessThan($catalogue);
});
