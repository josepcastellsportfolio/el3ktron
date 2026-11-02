<?php

namespace App\Orders;

use App\Enums\OrderDraftStatus;
use App\Models\OrderDraft;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns an order written by a person into a pending draft, and lets another
 * person correct and approve it. The extractor (rules or LLM) proposes; only
 * `approve` makes it an order, and only with every line matched to a product.
 */
final class OrderDraftService
{
    public function __construct(private readonly OrderExtractor $extractor) {}

    public function fromText(string $text, ?Store $store = null): OrderDraft
    {
        $lines = array_map(fn (ExtractedLine $line) => $this->match($line), $this->extractor->extract($text));

        return OrderDraft::create([
            'store_id' => $store?->id,
            'extractor' => $this->extractor->name(),
            'source_text' => $text,
            'lines' => $lines,
        ]);
    }

    /**
     * The reviewer's corrections: the full list of lines, by SKU.
     *
     * @param  list<array{sku: string, quantity: int}>  $lines
     */
    public function correct(OrderDraft $draft, array $lines): OrderDraft
    {
        $this->assertPending($draft);
        $products = Product::whereIn('sku', array_column($lines, 'sku'))->pluck('id', 'sku');

        $draft->update(['lines' => array_map(fn (array $line) => [
            'raw' => 'corregido por revisor',
            'quantity' => $line['quantity'],
            'sku' => $line['sku'],
            'ean' => null,
            'description' => null,
            'product_id' => $products[$line['sku']] ?? null,
        ], $lines)]);

        return $draft;
    }

    public function approve(OrderDraft $draft, User $reviewer): OrderDraft
    {
        $this->assertPending($draft);
        $unmatched = array_filter($draft->lines, fn (array $line) => $line['product_id'] === null);
        if ($draft->lines === [] || $unmatched !== []) {
            throw ValidationException::withMessages([
                'lines' => $draft->lines === []
                    ? 'The draft has no lines.'
                    : count($unmatched).' line(s) do not match a product. Correct them before approving.',
            ]);
        }

        return $this->review($draft, $reviewer, OrderDraftStatus::Approved);
    }

    public function reject(OrderDraft $draft, User $reviewer): OrderDraft
    {
        $this->assertPending($draft);

        return $this->review($draft, $reviewer, OrderDraftStatus::Rejected);
    }

    private function review(OrderDraft $draft, User $reviewer, OrderDraftStatus $status): OrderDraft
    {
        return DB::transaction(function () use ($draft, $reviewer, $status) {
            $draft->update(['status' => $status, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);

            return $draft;
        });
    }

    private function assertPending(OrderDraft $draft): void
    {
        if ($draft->status !== OrderDraftStatus::Pending) {
            throw ValidationException::withMessages(['status' => "The draft is already {$draft->status->value}."]);
        }
    }

    /** @return array{raw: string, quantity: int, sku: string|null, ean: string|null, description: string|null, product_id: int|null} */
    private function match(ExtractedLine $line): array
    {
        $product = match (true) {
            $line->sku !== null => Product::where('sku', $line->sku)->first(),
            $line->ean !== null => Product::where('ean', $line->ean)->first(),
            default => null, // free text: left for the reviewer to match
        };

        return [...$line->toArray(), 'sku' => $product->sku ?? $line->sku, 'product_id' => $product?->id];
    }
}
