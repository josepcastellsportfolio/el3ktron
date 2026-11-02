<?php

namespace App\Http\Resources;

use App\Models\Installation;
use App\Models\InstallationLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Installation */
class InstallationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store' => $this->store->code,
            'planogram' => $this->planogram->code,
            'status' => $this->status->value,
            'scheduled_for' => $this->scheduled_for?->toDateString(),
            'needs_review' => $this->needs_review,
            'review_reason' => $this->review_reason,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn (InstallationLine $line) => [
                'sku' => $line->product->sku,
                'quantity' => $line->quantity,
                'substitute_for' => $line->substituteFor?->sku,
            ])),
        ];
    }
}
