<?php

namespace App\Benchmark;

use App\Installations\Candidate;
use App\Warehouse\PickLine;
use App\Warehouse\Point;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * A made-up warehouse and a year of made-up installations, generated from a
 * seed so every run gives the same numbers. Nothing here is real data from
 * any company; the rates are parameters, printed with the results.
 */
final class SyntheticWarehouse
{
    /** @var array<int, Candidate> */
    public array $products = [];

    /** @var array<int, string> */
    public array $skus = [];

    /** @var array<int, Point> */
    public array $locations = [];

    /** @var list<array<int, int>> product id => quantity, one per installation */
    public array $installations = [];

    private Randomizer $random;

    public function __construct(
        public readonly int $seed,
        public readonly int $aisles,
        public readonly int $baysPerAisle,
        public readonly int $productCount = 2000,
        public readonly int $families = 40,
        public readonly int $installationCount = 200,
        public readonly int $minItems = 40,
        public readonly int $maxItems = 120,
        public readonly float $shortageRate = 0.06,
        public readonly float $discontinuedRate = 0.03,
    ) {
        $this->random = new Randomizer(new Mt19937($seed));
        $this->generateProducts();
        $this->generateInstallations();
    }

    private function generateProducts(): void
    {
        for ($id = 1; $id <= $this->productCount; $id++) {
            $roll = $this->random->getFloat(0, 1);
            $this->products[$id] = new Candidate(
                productId: $id,
                familyId: $this->random->getInt(1, $this->families),
                priceCents: $this->random->getInt(100, 5000),
                // Short products have fewer than the 2 units a planogram asks for at least.
                available: $roll < $this->shortageRate ? $this->random->getInt(0, 1) : $this->random->getInt(20, 400),
                active: $roll >= $this->shortageRate && $roll < $this->shortageRate + $this->discontinuedRate ? false : true,
            );
            $this->skus[$id] = sprintf('E3-%05d', $id);
            $this->locations[$id] = new Point($this->random->getInt(1, $this->aisles), $this->random->getInt(1, $this->baysPerAisle));
        }
    }

    private function generateInstallations(): void
    {
        $ids = array_keys($this->products);
        for ($i = 0; $i < $this->installationCount; $i++) {
            $count = $this->random->getInt($this->minItems, $this->maxItems);
            $picked = $this->random->pickArrayKeys(array_flip($ids), $count);
            $items = [];
            foreach ($picked as $id) {
                $items[$id] = $this->random->getInt(2, 12);
            }
            $this->installations[] = $items;
        }
    }

    /**
     * @param  array<int, int>  $items
     * @return list<PickLine>
     */
    public function pickLines(array $items): array
    {
        $lines = [];
        foreach ($items as $id => $quantity) {
            $point = $this->locations[$id];
            $lines[] = new PickLine($this->skus[$id], $this->skus[$id], "{$point->aisle}-{$point->bay}", $point, $quantity);
        }

        return $lines;
    }

    /** @return array<int, list<Candidate>> */
    public function byFamily(): array
    {
        $families = [];
        foreach ($this->products as $product) {
            $families[$product->familyId][] = $product;
        }

        return $families;
    }
}
