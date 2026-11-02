<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInstallationRequest;
use App\Http\Resources\InstallationResource;
use App\Installations\AvailabilityChecker;
use App\Installations\PickingList;
use App\Installations\ReviewConfirmation;
use App\Installations\StockReserver;
use App\Models\Installation;
use App\Models\Planogram;
use App\Models\Store;
use App\Warehouse\PickLine;
use Illuminate\Http\JsonResponse;

/**
 * An installation from draft to picking list: create, check availability,
 * confirm the review, reserve stock, get the picking list.
 */
class InstallationController extends Controller
{
    /**
     * Create a draft installation of a planogram for a store.
     */
    public function store(StoreInstallationRequest $request): JsonResponse
    {
        $installation = Installation::create([
            'store_id' => Store::where('code', $request->string('store'))->value('id'),
            'planogram_id' => Planogram::where('code', $request->string('planogram'))->value('id'),
            'scheduled_for' => $request->input('scheduled_for'),
        ]);

        return (new InstallationResource($this->load($installation)))->response()->setStatusCode(201);
    }

    /**
     * Show an installation with its lines.
     */
    public function show(Installation $installation): InstallationResource
    {
        return new InstallationResource($this->load($installation));
    }

    /**
     * Check availability: every planogram product is ok, substituted, or missing.
     *
     * Replaces any previous check. Substitutes and missing lines flag the
     * installation for review.
     */
    public function check(Installation $installation, AvailabilityChecker $checker): JsonResponse
    {
        $report = $checker->check($installation);

        return response()->json([
            'installation' => new InstallationResource($this->load($installation->refresh())),
            'report' => $report,
        ]);
    }

    /**
     * Confirm the review: a person accepts the substitutes and missing lines.
     */
    public function confirmReview(Installation $installation, ReviewConfirmation $confirmation): InstallationResource
    {
        $confirmation->confirm($installation);

        return new InstallationResource($this->load($installation->refresh()));
    }

    /**
     * Reserve the stock of a checked installation, all lines or none.
     */
    public function reserve(Installation $installation, StockReserver $reserver): InstallationResource
    {
        $reserver->reserve($installation);

        return new InstallationResource($this->load($installation->refresh()));
    }

    /**
     * The picking list in walking order, with the meters saved over catalogue order.
     */
    public function pickingList(Installation $installation, PickingList $pickingList): JsonResponse
    {
        $list = $pickingList->for($installation);

        return response()->json([
            'route_meters' => $list['route_meters'],
            'catalogue_order_meters' => $list['catalogue_order_meters'],
            'lines' => array_map(fn (PickLine $line) => [
                'location' => $line->locationCode,
                'sku' => $line->sku,
                'name' => $line->name,
                'quantity' => $line->quantity,
            ], $list['lines']),
        ]);
    }

    private function load(Installation $installation): Installation
    {
        return $installation->load(['store', 'planogram', 'lines.product', 'lines.substituteFor']);
    }
}
