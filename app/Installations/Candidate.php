<?php

namespace App\Installations;

/** What the substitute rule needs to know about a product. */
final readonly class Candidate
{
    public function __construct(
        public int $productId,
        public int $familyId,
        public int $priceCents,
        public int $available,
        public bool $active,
    ) {}
}
