<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Expense> */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'expense_date' => now()->toDateString(),
            'category' => fake()->randomElement(config('salon.expense_categories')),
            'description' => fake()->sentence(3),
            'amount' => fake()->numberBetween(1, 60) * 50,
            'payment_mode' => fake()->randomElement(Expense::PAYMENT_MODES),
            'user_id' => User::factory(),
        ];
    }
}
