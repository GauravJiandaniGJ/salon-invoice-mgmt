<?php

namespace Database\Factories;

use App\Models\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffMember> */
class StaffMemberFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->firstName(), 'is_active' => true];
    }
}
