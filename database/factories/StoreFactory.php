<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Store> */
class StoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('FD-##')),
            'name' => 'Ferretería '.$this->faker->lastName(),
            'city' => $this->faker->city(),
        ];
    }
}
