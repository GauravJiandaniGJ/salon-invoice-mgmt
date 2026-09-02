<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CustomerController extends Controller
{
    /** GET /customers/lookup?phone= (json) */
    public function lookup(Request $request): JsonResponse
    {
        try {
            $phone = PhoneNumber::normalise($request->query('phone'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['found' => false, 'customer' => null, 'normalised_phone' => null, 'error' => $e->getMessage()]);
        }

        $customer = Customer::where('phone', $phone)->withCount(['invoices as visits' => fn ($q) => $q->issued()])->first();

        if (! $customer) {
            return response()->json(['found' => false, 'customer' => null, 'normalised_phone' => $phone, 'error' => null]);
        }

        $last = $customer->invoices()->issued()->first();

        return response()->json([
            'found' => true,
            'customer' => [
                ...$this->row($customer),
                'notes' => $customer->notes,
                'last_invoice' => $last ? [
                    'id' => $last->id,
                    'invoice_number' => $last->invoice_number,
                    'total' => (float) $last->total,
                    'invoice_date' => $last->invoice_date->toDateString(),
                ] : null,
            ],
            'normalised_phone' => $phone,
            'error' => null,
        ]);
    }

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->withCount(['invoices as visits' => fn ($query) => $query->issued()])
            ->when($q !== '', function ($query) use ($q) {
                $digits = preg_replace('/\D+/', '', $q);
                $query->where(function ($w) use ($q, $digits) {
                    $w->where('name', 'like', "%{$q}%");
                    if ($digits !== '') {
                        $w->orWhere('phone', 'like', "%{$digits}%");
                    }
                });
            })
            ->orderByRaw('last_visit_at IS NULL')
            ->orderByDesc('last_visit_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Customer $customer) => $this->row($customer));

        return Inertia::render('customers/Index', [
            'filters' => ['q' => $q],
            'customers' => $customers,
        ]);
    }

    public function show(Customer $customer): Response
    {
        $customer->loadCount(['invoices as visits' => fn ($q) => $q->issued()]);

        $invoices = $customer->invoices()
            ->with(['customer', 'items'])
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Invoice $invoice) => InvoiceService::toRow($invoice));

        return Inertia::render('customers/Show', [
            'customer' => [
                ...$this->row($customer),
                'notes' => $customer->notes,
                'created_at' => $customer->created_at->toISOString(),
            ],
            'invoices' => $invoices,
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->normalised());

        return back()->with('success', 'Customer updated.');
    }

    /** CustomerRow shape. Expects `visits` count loaded. @return array<string, mixed> */
    protected function row(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'phone_display' => $customer->phone_display,
            'gender' => $customer->gender,
            'total_spent' => (float) $customer->total_spent,
            'visits' => (int) ($customer->visits ?? 0),
            'last_visit_at' => $customer->last_visit_at?->toISOString(),
        ];
    }
}
