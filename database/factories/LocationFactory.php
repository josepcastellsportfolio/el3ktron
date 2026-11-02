<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        $aisle = $this->faker->numberBetween(1, 12);
        $bay = $this->faker->numberBetween(1, 20);
        $level = $this->faker->numberBetween(1, 4);

        return [
            'code' => sprintf('A%02d-%02d-%d-%s', $aisle, $bay, $level, $this->faker->unique()->lexify('???')),
            'aisle' => $aisle,
            'bay' => $bay,
            'level' => $level,
        ];
    }
}
