<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CorrectOrderDraftRequest;
use App\Http\Requests\StoreOrderDraftRequest;
use App\Http\Resources\OrderDraftResource;
use App\Models\OrderDraft;
use App\Models\Store;
use App\Orders\OrderDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Orders written by people, read by an extractor, approved by a person.
 */
class OrderDraftController extends Controller
{
    /**
     * Read an order from text and store it as a pending draft.
     */
    public function store(StoreOrderDraftRequest $request, OrderDraftService $service): JsonResponse
    {
        $store = $request->filled('store') ? Store::where('code', $request->string('store'))->first() : null;
        $draft = $service->fromText($request->string('text')->toString(), $store);

        return (new OrderDraftResource($draft->load('store')))->response()->setStatusCode(201);
    }

    /**
     * Show a draft.
     */
    public function show(OrderDraft $draft): OrderDraftResource
    {
        return new OrderDraftResource($draft->load('store'));
    }

    /**
     * Replace the lines with the reviewer's corrections.
     */
    public function update(CorrectOrderDraftRequest $request, OrderDraft $draft, OrderDraftService $service): OrderDraftResource
    {
        /** @var list<array{sku: string, quantity: int}> $lines */
        $lines = $request->validated('lines');

        return new OrderDraftResource($service->correct($draft, $lines)->load('store'));
    }

    /**
     * Approve: every line must match a product.
     */
    public function approve(Request $request, OrderDraft $draft, OrderDraftService $service): OrderDraftResource
    {
        return new OrderDraftResource($service->approve($draft, $request->user())->load('store'));
    }

    /**
     * Reject the draft.
     */
    public function reject(Request $request, OrderDraft $draft, OrderDraftService $service): OrderDraftResource
    {
        return new OrderDraftResource($service->reject($draft, $request->user())->load('store'));
    }
}
