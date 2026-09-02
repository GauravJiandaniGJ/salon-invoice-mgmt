<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->firstName().' '.fake()->lastName(),
            'phone' => '91'.fake()->unique()->numerify('9#########'),
            'gender' => fake()->randomElement(['female', 'male', null]),
            'notes' => null,
            'last_visit_at' => null,
            'total_spent' => 0,
        ];
    }
}
