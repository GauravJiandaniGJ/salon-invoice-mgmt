<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceItem> */
class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        $price = fake()->numberBetween(1, 40) * 50;

        return [
            'invoice_id' => Invoice::factory(),
            'service_id' => null,
            'description' => ucfirst(fake()->words(2, true)),
            'unit_price' => $price,
            'quantity' => 1,
            'line_total' => $price,
            'sort_order' => 1,
        ];
    }
}
