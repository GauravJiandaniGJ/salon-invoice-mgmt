<?php

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\ServiceCatalogSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\UserSeeder;

test('demo seeder creates consistent data and skips on second run', function () {
    $this->seed([SettingsSeeder::class, UserSeeder::class, ServiceCatalogSeeder::class]);

    $this->seed(DemoDataSeeder::class);

    expect(Customer::count())->toBe(20)
        ->and(Invoice::count())->toBe(60)
        ->and(Expense::count())->toBe(15)
        ->and(Invoice::void()->count())->toBeGreaterThan(0)
        ->and(Invoice::query()->orderBy('id')->pluck('invoice_number')->first())->toBe('WS-0001')
        ->and(Invoice::query()->orderByDesc('id')->pluck('invoice_number')->first())->toBe('WS-0060');

    foreach (Invoice::with('items')->get() as $invoice) {
        expect($invoice->items)->not->toBeEmpty();

        $subtotal = round($invoice->items->sum(fn ($i) => (float) $i->line_total), 2);
        $taxable = $subtotal - (float) $invoice->discount_amount;
        $raw = $taxable + (float) $invoice->tax_amount;

        expect((float) $invoice->subtotal)->toBe($subtotal)
            ->and((float) $invoice->total)->toBe(round($raw, 0))
            ->and(round((float) $invoice->total - $raw, 2))->toBe((float) $invoice->round_off)
            ->and((float) $invoice->discount_amount)->toBeLessThanOrEqual($subtotal)
            ->and($invoice->invoice_date->gte(now()->subDays(30)->startOfDay()))->toBeTrue();

        foreach ($invoice->items as $item) {
            expect($item->service_id)->not->toBeNull()
                ->and((float) $item->line_total)->toBe(round((float) $item->unit_price * (float) $item->quantity, 2));
        }
    }

    // customer denormalised totals match issued invoices
    foreach (Customer::all() as $customer) {
        $expected = round(Invoice::issued()->where('customer_id', $customer->id)->sum('total'), 2);
        expect((float) $customer->total_spent)->toBe($expected);
    }

    $this->seed(DemoDataSeeder::class);
    expect(Invoice::count())->toBe(60)->and(Customer::count())->toBe(20);
});
