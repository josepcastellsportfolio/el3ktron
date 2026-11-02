<?php

namespace App\Orders;

/** One line an extractor read from an order: an identifier (SKU, EAN or text) and a quantity. */
final readonly class ExtractedLine
{
    public function __construct(
        public string $raw,
        public int $quantity,
        public ?string $sku = null,
        public ?string $ean = null,
        public ?string $description = null,
    ) {}

    /** @return array{raw: string, quantity: int, sku: string|null, ean: string|null, description: string|null} */
    public function toArray(): array
    {
        return ['raw' => $this->raw, 'quantity' => $this->quantity, 'sku' => $this->sku, 'ean' => $this->ean, 'description' => $this->description];
    }
}
