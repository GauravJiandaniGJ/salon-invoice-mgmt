<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\Setting;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Local/dev only: 20 customers, 60 invoices over the last 30 days, 15 expenses.
 * Skips itself when invoices already exist. Never runs in production.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        if (Invoice::query()->exists()) {
            $this->command?->warn('DemoDataSeeder skipped: invoices already exist.');

            return;
        }

        $services = Service::query()->active()->where('price', '>', 0)->get();
        if ($services->isEmpty()) {
            $this->command?->error('DemoDataSeeder needs the service catalog. Run ServiceCatalogSeeder first.');

            return;
        }

        $users = User::query()->where('is_active', true)->get();
        if ($users->isEmpty()) {
            $users = collect([User::create([
                'name' => 'Salon Owner',
                'email' => 'owner@wowsalon.local',
                'password' => env('SEED_OWNER_PASSWORD', 'password'),
                'role' => User::ROLE_OWNER,
            ])]);
        }

        $staff = StaffMember::query()->where('is_active', true)->get();
        if ($staff->isEmpty()) {
            foreach ([['Shubham', 10], ['Priya', 12.5], ['Raj', 8]] as [$name, $pct]) {
                $staff->push(StaffMember::create(['name' => $name, 'commission_percent' => $pct, 'is_active' => true]));
            }
        }

        $prefix = (string) Setting::get('invoice_prefix', 'WS');
        $taxRate = (float) Setting::get('tax_rate', 0);
        $modes = Invoice::PAYMENT_MODES;

        DB::transaction(function () use ($services, $users, $prefix, $taxRate, $modes, $staff) {
            // No Faker here: the production image ships without dev dependencies.
            $names = [
                'Divyanshu Sharma', 'Priya Patel', 'Rahul Mehta', 'Sneha Joshi', 'Aarav Desai',
                'Kavya Nair', 'Rohan Verma', 'Ananya Iyer', 'Vikram Singh', 'Pooja Shah',
                'Karan Kapoor', 'Meera Reddy', 'Aditya Rao', 'Nisha Gupta', 'Siddharth Jain',
                'Riya Malhotra', 'Arjun Kulkarni', 'Tanvi Bhatt', 'Manish Agarwal', 'Isha Chauhan',
            ];
            $customers = collect($names)->map(fn (string $name, int $i) => Customer::create([
                'name' => $name,
                'phone' => '91'.(9800000000 + ($i + 1) * 1234567 % 99999999 + 10000000),
                'gender' => match ($i % 3) {
                    0 => 'female', 1 => 'male', default => null
                },
            ]));

            // 60 dates over the last 30 days, oldest first so numbering follows time.
            $dates = collect(range(1, 60))
                ->map(fn () => now()->subDays(random_int(0, 29))->startOfDay())
                ->sort()
                ->values();

            $number = 0;
            foreach ($dates as $i => $date) {
                $number++;
                $customer = $customers->random();
                $picked = $services->random(random_int(1, 4));

                $items = [];
                foreach ($picked as $sort => $service) {
                    $qty = 1;
                    $unit = (float) $service->price;
                    $items[] = [
                        'service_id' => $service->id,
                        'description' => $service->display_name,
                        'unit_price' => $unit,
                        'quantity' => $qty,
                        'line_total' => round($unit * $qty, 2),
                        'sort_order' => $sort + 1,
                    ];
                }

                $subtotal = round(array_sum(array_column($items, 'line_total')), 2);
                $discountType = null;
                $discountValue = 0.0;
                $discountAmount = 0.0;
                if ($i % 5 === 0) {
                    $discountType = 'percent';
                    $discountValue = 10.0;
                    $discountAmount = round($subtotal * $discountValue / 100, 2);
                } elseif ($i % 7 === 0) {
                    $discountType = 'flat';
                    $discountValue = 50.0;
                    $discountAmount = min(round($discountValue, 2), $subtotal);
                }
                $taxable = $subtotal - $discountAmount;
                $taxAmount = round($taxable * $taxRate / 100, 2);
                $rawTotal = $taxable + $taxAmount;
                $total = round($rawTotal, 0);
                $roundOff = round($total - $rawTotal, 2);

                $isVoid = $i % 15 === 7; // a few voids
                $user = $users->random();
                $servedBy = $staff->random();

                $invoice = Invoice::create([
                    'invoice_number' => $prefix.'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                    'public_code' => $this->uniqueCode(),
                    'customer_id' => $customer->id,
                    'user_id' => $user->id,
                    'staff_member_id' => $servedBy->id,
                    'invoice_date' => $date->toDateString(),
                    'subtotal' => $subtotal,
                    'discount_type' => $discountType,
                    'discount_value' => $discountValue,
                    'discount_amount' => $discountAmount,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'round_off' => $roundOff,
                    'total' => $total,
                    'payment_mode' => $modes[array_rand($modes)],
                    'payment_status' => 'paid',
                    'notes' => null,
                    'status' => $isVoid ? Invoice::STATUS_VOID : Invoice::STATUS_ISSUED,
                    'void_reason' => $isVoid ? 'Demo: entered by mistake' : null,
                    'voided_at' => $isVoid ? $date->copy()->addHours(2) : null,
                    'voided_by' => $isVoid ? $user->id : null,
                    'whatsapp_sent_at' => $i % 3 === 0 ? null : $date->copy()->addMinutes(random_int(5, 600)),
                    'created_at' => $date->copy()->addHours(random_int(10, 20)),
                    'updated_at' => $date->copy()->addHours(random_int(10, 20)),
                ]);

                foreach ($items as $k => $item) {
                    // most lines by the invoice's barber, occasionally another one
                    $lineStaff = ($k > 0 && random_int(1, 4) === 1) ? $staff->random() : $servedBy;
                    InvoiceItem::create([...$item, 'invoice_id' => $invoice->id, 'staff_member_id' => $lineStaff->id]);
                }

                if (! $isVoid) {
                    $customer->total_spent = (float) $customer->total_spent + $total;
                    if (! $customer->last_visit_at || $customer->last_visit_at->lt($invoice->created_at)) {
                        $customer->last_visit_at = $invoice->created_at;
                    }
                    $customer->save();
                }
            }

            for ($i = 0; $i < 15; $i++) {
                $category = config('salon.expense_categories')[$i % count(config('salon.expense_categories'))];
                Expense::create([
                    'expense_date' => now()->subDays(random_int(0, 29))->toDateString(),
                    'category' => $category,
                    'description' => $category.' expense',
                    'amount' => random_int(1, 60) * 50,
                    'payment_mode' => $modes[$i % count($modes)],
                    'user_id' => $users->random()->id,
                ]);
            }
        });
    }

    protected function uniqueCode(): string
    {
        do {
            $code = Str::random(10);
        } while (Invoice::where('public_code', $code)->exists());

        return $code;
    }
}
