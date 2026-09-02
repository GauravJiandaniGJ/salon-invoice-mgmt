<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Bare invoice row with consistent totals and no items. Prefer
 * InvoiceService::create() in tests that need real billing behaviour.
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->numberBetween(2, 80) * 50;

        return [
            'invoice_number' => 'WS-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 4, '0', STR_PAD_LEFT),
            'public_code' => Str::random(10),
            'customer_id' => Customer::factory(),
            'user_id' => User::factory(),
            'staff_member_id' => null,
            'invoice_date' => now()->toDateString(),
            'subtotal' => $total,
            'discount_type' => null,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'round_off' => 0,
            'total' => $total,
            'payment_mode' => fake()->randomElement(Invoice::PAYMENT_MODES),
            'payment_status' => 'paid',
            'status' => Invoice::STATUS_ISSUED,
        ];
    }

    public function void(string $reason = 'Test void'): static
    {
        return $this->state(fn () => [
            'status' => Invoice::STATUS_VOID,
            'void_reason' => $reason,
            'voided_at' => now(),
        ]);
    }
}
