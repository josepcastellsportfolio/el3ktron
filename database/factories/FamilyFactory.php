<?php

namespace Database\Factories;

use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Family> */
class FamilyFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => strtoupper($this->faker->unique()->bothify('F??##')), 'name' => $this->faker->words(2, true)];
    }
}
