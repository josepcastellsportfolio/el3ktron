<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'sku' => $this->sku,
            'ean' => $this->ean,
            'name' => $this->name,
            'family' => $this->whenLoaded('family', fn () => $this->family->code),
            'price_cents' => $this->price_cents,
            'status' => $this->status->value,
            /** Free units across all locations; present when the query asked for it. */
            'available' => $this->when(isset($this->available), fn () => (int) $this->available),
            'synced_at' => $this->synced_at?->toIso8601String(),
        ];
    }
}
