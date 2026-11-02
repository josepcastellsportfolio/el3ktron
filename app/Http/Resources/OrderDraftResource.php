<?php

namespace App\Http\Resources;

use App\Models\OrderDraft;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderDraft */
class OrderDraftResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store' => $this->store?->code,
            'extractor' => $this->extractor,
            'status' => $this->status->value,
            'lines' => $this->lines,
            'unmatched_lines' => count(array_filter($this->lines, fn (array $line) => $line['product_id'] === null)),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
