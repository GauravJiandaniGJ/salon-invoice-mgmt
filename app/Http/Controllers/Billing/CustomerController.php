<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreCustomerRequest;
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
    /**
     * GET /customers/lookup?phone=   → exact match on a normalised number.
     * GET /customers/lookup?q=       → up to 8 suggestions by name or phone fragment.
     */
    public function lookup(Request $request): JsonResponse
    {
        if ($request->filled('q') && ! $request->filled('phone')) {
            return $this->suggestions(trim((string) $request->query('q')));
        }

        try {
            $phone = PhoneNumber::normalise($request->query('phone'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['found' => false, 'customer' => null, 'normalised_phone' => null, 'error' => $e->getMessage()]);
        }

        $customer = Customer::where('phone', $phone)->withCount(['invoices as visits' => fn ($q) => $q->issued()])->first();

        if (! $customer) {
            return response()->json(['found' => false, 'customer' => null, 'normalised_phone' => $phone, 'error' => null]);
        }

        return response()->json([
            'found' => true,
            'customer' => $this->lookupShape($customer),
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

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->normalised());

        return redirect()->route('customers.show', $customer)->with('success', 'Customer added.');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->normalised());

        return back()->with('success', 'Customer updated.');
    }

    protected function suggestions(string $q): JsonResponse
    {
        $empty = ['found' => false, 'customer' => null, 'normalised_phone' => null, 'error' => null];

        if (mb_strlen($q) < 2) {
            return response()->json([...$empty, 'matches' => []]);
        }

        $digits = preg_replace('/\D+/', '', $q) ?? '';

        $matches = Customer::query()
            ->withCount(['invoices as visits' => fn ($query) => $query->issued()])
            ->where(function ($w) use ($q, $digits) {
                $w->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($q).'%']);
                if (strlen($digits) >= 2) {
                    $w->orWhere('phone', 'like', "%{$digits}%");
                }
            })
            ->orderByRaw('last_visit_at IS NULL')
            ->orderByDesc('last_visit_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Customer $customer) => $this->lookupShape($customer))
            ->values();

        return response()->json([...$empty, 'matches' => $matches]);
    }

    /** CustomerLookup shape (CustomerRow + notes + last_invoice). @return array<string, mixed> */
    protected function lookupShape(Customer $customer): array
    {
        $last = $customer->invoices()->issued()->first();

        return [
            ...$this->row($customer),
            'notes' => $customer->notes,
            'last_invoice' => $last ? [
                'id' => $last->id,
                'invoice_number' => $last->invoice_number,
                'total' => (float) $last->total,
                'invoice_date' => $last->invoice_date->toDateString(),
            ] : null,
        ];
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
