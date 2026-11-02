<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Family;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sku' => sprintf('E3-%05d', $this->faker->unique()->numberBetween(1, 99999)),
            'ean' => null,
            'name' => $this->faker->words(3, true),
            'family_id' => Family::factory(),
            'price_cents' => $this->faker->numberBetween(100, 5000),
            'status' => ProductStatus::Active,
        ];
    }

    public function discontinued(): static
    {
        return $this->state(['status' => ProductStatus::Discontinued]);
    }
}
