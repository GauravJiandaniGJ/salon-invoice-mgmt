<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreInvoiceRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\StaffMember;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    public function create(Request $request): Response
    {
        $catalog = ServiceCategory::query()
            ->where('is_active', true)
            ->with(['services' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (ServiceCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'audience' => $category->audience,
                'services' => $category->services->map(fn (Service $service) => [
                    'id' => $service->id,
                    'group_name' => $service->group_name,
                    'name' => $service->name,
                    'display_name' => $service->display_name,
                    'description' => $service->description,
                    'price' => (float) $service->price,
                    'price_max' => $service->price_max === null ? null : (float) $service->price_max,
                    'duration_minutes' => $service->duration_minutes,
                ])->values(),
            ])->values();

        return Inertia::render('bills/New', [
            'catalog' => $catalog,
            'staff_members' => StaffMember::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'payment_modes' => Invoice::PAYMENT_MODES,
            'tax_rate' => (float) Setting::get('tax_rate', 0),
            'today' => now()->toDateString(),
            'can_edit_date' => $request->user()->isOwner(),
            'prefill' => $this->prefill($request),
        ]);
    }

    public function store(StoreInvoiceRequest $request, InvoiceService $invoices): RedirectResponse
    {
        $invoice = $invoices->create($request->validated(), $request->user());

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', "Invoice {$invoice->invoice_number} saved.");
    }

    /** @return array<string, mixed>|null */
    protected function prefill(Request $request): ?array
    {
        if ($request->filled('duplicate')) {
            $invoice = Invoice::with(['customer', 'items'])->find($request->integer('duplicate'));

            if ($invoice) {
                return [
                    'customer' => $this->customerPrefill($invoice->customer),
                    'staff_member_id' => $invoice->staff_member_id,
                    'items' => $invoice->items->map(fn ($item) => [
                        'service_id' => $item->service_id,
                        'description' => $item->description,
                        'unit_price' => (float) $item->unit_price,
                        'quantity' => (float) $item->quantity,
                    ])->values()->all(),
                    'discount_type' => $invoice->discount_type,
                    'discount_value' => (float) $invoice->discount_value,
                    'payment_mode' => $invoice->payment_mode,
                    'notes' => (string) $invoice->notes,
                ];
            }
        }

        if ($request->filled('customer_id')) {
            $customer = Customer::find($request->integer('customer_id'));

            if ($customer) {
                return [
                    'customer' => $this->customerPrefill($customer),
                    'staff_member_id' => null,
                    'items' => [],
                    'discount_type' => null,
                    'discount_value' => 0,
                    'payment_mode' => 'cash',
                    'notes' => '',
                ];
            }
        }

        return null;
    }

    /** @return array{phone: string, name: string, gender: ?string} */
    protected function customerPrefill(Customer $customer): array
    {
        return ['phone' => $customer->phone, 'name' => $customer->name, 'gender' => $customer->gender];
    }
}
