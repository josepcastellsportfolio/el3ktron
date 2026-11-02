<?php

namespace App\Installations;

use App\Enums\InstallationStatus;
use App\Models\Installation;
use App\Models\Reservation;
use App\Warehouse\PickingRoute;
use App\Warehouse\PickLine;
use App\Warehouse\WarehouseLayout;

/**
 * The picking list of a reserved installation, in walking order, with the
 * meters it saves over the catalogue order: the measurable part.
 */
final class PickingList
{
    public function __construct(private readonly WarehouseLayout $layout) {}

    /** @return array{lines: list<PickLine>, route_meters: float, catalogue_order_meters: float} */
    public function for(Installation $installation): array
    {
        if ($installation->status !== InstallationStatus::Reserved) {
            throw new InstallationException("Installation {$installation->id} is {$installation->status->value}; reserve it first.");
        }

        $lines = Reservation::query()
            ->whereIn('installation_line_id', $installation->lines()->pluck('id'))
            ->with(['stock.location', 'stock.product'])
            ->get()
            ->map(fn (Reservation $r) => new PickLine(
                $r->stock->product->sku,
                $r->stock->product->name,
                $r->stock->location->code,
                $r->stock->location->point(),
                $r->quantity,
            ))
            ->all();

        $route = PickingRoute::serpentine($lines);

        return [
            'lines' => $route,
            'route_meters' => round($this->layout->routeLength(PickingRoute::points($route)), 1),
            'catalogue_order_meters' => round($this->layout->routeLength(PickingRoute::points(PickingRoute::catalogueOrder($lines))), 1),
        ];
    }
}
