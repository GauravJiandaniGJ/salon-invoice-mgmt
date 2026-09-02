<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InvoiceService
{
    public function __construct(protected PdfRenderer $pdf) {}

    /**
     * @param  array<string, mixed>  $data  validated StoreInvoiceRequest data
     */
    public function create(array $data, User $user): Invoice
    {
        try {
            $invoice = $this->insert($data, $user);
        } catch (UniqueConstraintViolationException) {
            // invoice_number / public_code collision under concurrency — retry once
            $invoice = $this->insert($data, $user);
        }

        $this->pdf->render($invoice);

        return $invoice->fresh(['customer', 'items', 'staffMember', 'user']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function insert(array $data, User $user): Invoice
    {
        return DB::transaction(function () use ($data, $user) {
            $customer = $this->findOrCreateCustomer($data['customer']);

            $items = collect($data['items'])->values()->map(function (array $item, int $index) {
                $service = ! empty($item['service_id']) ? Service::find($item['service_id']) : null;
                $description = trim((string) ($item['description'] ?? ''));

                return [
                    'service_id' => $service?->id,
                    'description' => $description !== '' ? $description : ($service?->display_name ?? 'Service'),
                    'unit_price' => (float) $item['unit_price'],
                    'quantity' => (float) $item['quantity'],
                    'sort_order' => $index + 1,
                ];
            })->all();

            $taxRate = (float) Setting::get('tax_rate', 0);
            $totals = InvoiceTotals::calculate($items, $data['discount_type'] ?? null, (float) ($data['discount_value'] ?? 0), $taxRate);

            $invoiceDate = $user->isOwner() && ! empty($data['invoice_date'])
                ? $data['invoice_date']
                : now()->toDateString();

            $invoice = Invoice::create([
                'invoice_number' => InvoiceNumber::next((string) Setting::get('invoice_prefix', 'WS')),
                'public_code' => $this->uniquePublicCode(),
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'staff_member_id' => $data['staff_member_id'] ?? null,
                'invoice_date' => $invoiceDate,
                'subtotal' => $totals['subtotal'],
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => (float) ($data['discount_value'] ?? 0),
                'discount_amount' => $totals['discount_amount'],
                'tax_rate' => $taxRate,
                'tax_amount' => $totals['tax_amount'],
                'round_off' => $totals['round_off'],
                'total' => $totals['total'],
                'payment_mode' => $data['payment_mode'],
                'payment_status' => $data['payment_status'] ?? 'paid',
                'notes' => $data['notes'] ?? null,
                'status' => Invoice::STATUS_ISSUED,
            ]);

            $invoice->items()->createMany($totals['items']);

            $customer->forceFill([
                'last_visit_at' => now(),
                'total_spent' => (float) $customer->total_spent + $totals['total'],
            ])->save();

            return $invoice;
        });
    }

    public function void(Invoice $invoice, User $by, string $reason): void
    {
        DB::transaction(function () use ($invoice, $by, $reason) {
            $invoice->forceFill([
                'status' => Invoice::STATUS_VOID,
                'void_reason' => $reason,
                'voided_at' => now(),
                'voided_by' => $by->id,
            ])->save();

            $customer = $invoice->customer;
            $customer->forceFill([
                'total_spent' => max(0, (float) $customer->total_spent - (float) $invoice->total),
            ])->save();
        });

        $this->pdf->render($invoice);

        Log::info('Invoice voided', ['invoice' => $invoice->invoice_number, 'by' => $by->id, 'reason' => $reason]);
    }

    /**
     * @param  array{phone: string, name?: string|null, gender?: string|null}  $data
     */
    protected function findOrCreateCustomer(array $data): Customer
    {
        $phone = PhoneNumber::normalise($data['phone']);
        $customer = Customer::where('phone', $phone)->first();

        if (! $customer) {
            return Customer::create([
                'name' => trim((string) $data['name']),
                'phone' => $phone,
                'gender' => $data['gender'] ?? null,
            ]);
        }

        // Keep the existing name; only fill gender when the record has none.
        if (! $customer->gender && ! empty($data['gender'])) {
            $customer->update(['gender' => $data['gender']]);
        }

        return $customer;
    }

    protected function uniquePublicCode(): string
    {
        do {
            $code = Str::random(10); // [A-Za-z0-9]
        } while (Invoice::where('public_code', $code)->exists());

        return $code;
    }

    /**
     * InvoiceRow shape (docs/CONTRACT.md) — requires customer + items loaded.
     *
     * @return array<string, mixed>
     */
    public static function toRow(Invoice $invoice): array
    {
        $descriptions = $invoice->items->pluck('description');
        $summary = $descriptions->take(2)->implode(', ');
        if ($descriptions->count() > 2) {
            $summary .= ' +'.($descriptions->count() - 2);
        }

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date->toDateString(),
            'customer' => [
                'id' => $invoice->customer->id,
                'name' => $invoice->customer->name,
                'phone_display' => $invoice->customer->phone_display,
            ],
            'items_summary' => $summary,
            'total' => (float) $invoice->total,
            'payment_mode' => $invoice->payment_mode,
            'payment_status' => $invoice->payment_status,
            'status' => $invoice->status,
            'whatsapp_sent_at' => $invoice->whatsapp_sent_at?->toISOString(),
        ];
    }
}
