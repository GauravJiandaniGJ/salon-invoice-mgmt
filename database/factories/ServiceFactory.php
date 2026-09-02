<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_category_id' => ServiceCategory::factory(),
            'group_name' => null,
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'price' => fake()->numberBetween(1, 60) * 50,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
