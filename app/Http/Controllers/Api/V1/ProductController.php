<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * The catalogue as integrators and the sales apps read it.
 */
class ProductController extends Controller
{
    /**
     * List products, filtered and paginated, with their free stock.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'family' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,discontinued'],
            /** Matches the start of the SKU or EAN, or any part of the name. */
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $products = Product::query()
            ->with('family')
            ->withSum('stock as available', DB::raw('quantity - reserved'))
            ->when($filters['family'] ?? null, fn (Builder $q, string $family) => $q->whereHas('family', fn (Builder $f) => $f->where('code', $family)))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $w) => $w
                ->where('sku', 'like', $term.'%')
                ->orWhere('ean', 'like', $term.'%')
                ->orWhere('name', 'like', '%'.$term.'%')))
            ->orderBy('sku')
            ->paginate($filters['per_page'] ?? 50)
            ->withQueryString();

        return ProductResource::collection($products);
    }

    /**
     * One product by SKU.
     */
    public function show(string $sku): ProductResource
    {
        $product = Product::query()
            ->with('family')
            ->withSum('stock as available', DB::raw('quantity - reserved'))
            ->where('sku', $sku)
            ->firstOrFail();

        return new ProductResource($product);
    }
}
