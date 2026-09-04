<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\InvoiceService;
use App\Services\PdfRenderer;
use App\Services\WhatsApp\CloudApiSender;
use App\Services\WhatsApp\DeviceLinks;
use App\Services\WhatsApp\InvoiceLink;
use App\Services\WhatsApp\MessageTemplate;
use App\Services\WhatsApp\WhatsAppSender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $invoices = $this->filtered($filters)
            ->with(['customer', 'items'])
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Invoice $invoice) => InvoiceService::toRow($invoice));

        return Inertia::render('invoices/Index', [
            'filters' => $filters,
            'invoices' => $invoices,
        ]);
    }

    /** GET /invoices/export.csv (owner) */
    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $filename = "invoices-{$filters['from']}-to-{$filters['to']}.csv";

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Invoice', 'Date', 'Customer', 'Phone', 'Items', 'Subtotal', 'Discount', 'Tax', 'Total', 'Payment', 'Status', 'Sent']);

            $this->filtered($filters)
                ->with(['customer', 'items'])
                ->orderBy('invoice_date')->orderBy('id')
                ->lazy()
                ->each(function (Invoice $invoice) use ($out) {
                    fputcsv($out, [
                        $invoice->invoice_number,
                        $invoice->invoice_date->toDateString(),
                        $invoice->customer->name,
                        $invoice->customer->phone_display,
                        $invoice->items->pluck('description')->implode('; '),
                        (float) $invoice->subtotal,
                        (float) $invoice->discount_amount,
                        (float) $invoice->tax_amount,
                        (float) $invoice->total,
                        $invoice->payment_mode,
                        $invoice->status,
                        $invoice->whatsapp_sent_at ? 'yes' : 'no',
                    ]);
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ---- B2: show / markSent / void / pdf ----

    public function show(Request $request, Invoice $invoice, WhatsAppSender $sender, MessageTemplate $template): Response
    {
        $invoice->load(['customer', 'items', 'staffMember', 'user', 'voidedBy']);

        $message = $template->render((string) Setting::get('whatsapp_template'), $invoice);
        $phone = $invoice->customer->phone;

        return Inertia::render('invoices/Show', [
            'invoice' => self::detail($invoice),
            'whatsapp_web_url' => DeviceLinks::web($phone, $message),
            'whatsapp_mobile_url' => DeviceLinks::mobile($phone, $message),
            'whatsapp_mode' => $sender instanceof CloudApiSender ? 'cloud' : 'link',
            'whatsapp_message' => $message,
            'public_url' => InvoiceLink::publicUrl($invoice),
            'pdf_url' => route('invoices.pdf', $invoice, absolute: false),
            'app_url_missing' => InvoiceLink::appUrlMissing(),
            'can_void' => $request->user()->isOwner() && ! $invoice->isVoid(),
        ]);
    }

    /**
     * POST /invoices/{invoice}/send (json) → SendResponse.
     * Cloud driver: sends server-side and marks sent. Link driver (or any failure):
     * returns the device-appropriate click-to-chat URL for the browser to open.
     */
    public function send(Request $request, Invoice $invoice, WhatsAppSender $sender, MessageTemplate $template): JsonResponse
    {
        $invoice->load(['customer', 'items']);

        if ($invoice->isVoid()) {
            return response()->json([
                'sent' => false,
                'whatsapp_sent_at' => $invoice->whatsapp_sent_at?->toISOString(),
                'fallback_url' => null,
                'error' => 'Void invoices cannot be sent.',
            ], 422);
        }

        $message = $template->render((string) Setting::get('whatsapp_template'), $invoice);
        $fallback = DeviceLinks::forRequest($request, $invoice->customer->phone, $message);

        if (! $sender instanceof CloudApiSender) {
            return response()->json([
                'sent' => false,
                'whatsapp_sent_at' => $invoice->whatsapp_sent_at?->toISOString(),
                'fallback_url' => $fallback,
                'error' => null,
            ]);
        }

        try {
            $sender->send($invoice, $message);
        } catch (Throwable $e) {
            Log::warning('WhatsApp Cloud API send failed', [
                'invoice' => $invoice->invoice_number,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'sent' => false,
                'whatsapp_sent_at' => $invoice->whatsapp_sent_at?->toISOString(),
                'fallback_url' => $fallback,
                'error' => $e->getMessage(),
            ]);
        }

        if (! $invoice->whatsapp_sent_at) {
            $invoice->forceFill(['whatsapp_sent_at' => now()])->save();
        }

        Log::info('WhatsApp sent via Cloud API', [
            'invoice' => $invoice->invoice_number,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'sent' => true,
            'whatsapp_sent_at' => $invoice->whatsapp_sent_at->toISOString(),
            'fallback_url' => null,
            'error' => null,
        ]);
    }

    /** Idempotent: records the first send click only. */
    public function markSent(Request $request, Invoice $invoice): JsonResponse
    {
        if (! $invoice->whatsapp_sent_at) {
            $invoice->forceFill(['whatsapp_sent_at' => now()])->save();
        }

        Log::info('WhatsApp send clicked', [
            'invoice' => $invoice->invoice_number,
            'user_id' => $request->user()->id,
        ]);

        Activity::log('invoice.sent', 'Sent to '.$invoice->customer->name, $invoice, null, $invoice->invoice_number);

        return response()->json(['whatsapp_sent_at' => $invoice->whatsapp_sent_at->toISOString()]);
    }

    public function void(Request $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);

        if ($invoice->isVoid()) {
            return back()->with('error', 'This invoice is already void.');
        }

        $service->void($invoice, $request->user(), $data['reason']);

        return back()->with('success', "Invoice {$invoice->invoice_number} voided.");
    }

    public function pdf(Invoice $invoice, PdfRenderer $renderer): SymfonyResponse
    {
        return $renderer->download($invoice);
    }

    /** Shapes an Invoice as the `InvoiceDetail` TS type. */
    public static function detail(Invoice $invoice): array
    {
        $invoice->loadMissing(['customer', 'items', 'staffMember', 'user', 'voidedBy']);

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'public_code' => $invoice->public_code,
            'invoice_date' => $invoice->invoice_date->format('Y-m-d'),
            'customer' => [
                'id' => $invoice->customer->id,
                'name' => $invoice->customer->name,
                'phone' => $invoice->customer->phone,
                'phone_display' => $invoice->customer->phone_display,
                'gender' => $invoice->customer->gender,
            ],
            'staff_member' => $invoice->staffMember ? ['id' => $invoice->staffMember->id, 'name' => $invoice->staffMember->name] : null,
            'billed_by' => ['id' => $invoice->user->id, 'name' => $invoice->user->name],
            'items' => $invoice->items->map(fn ($item) => [
                'id' => $item->id,
                'service_id' => $item->service_id,
                'staff_member' => $item->staffMember ? ['id' => $item->staffMember->id, 'name' => $item->staffMember->name] : null,
                'description' => $item->description,
                'unit_price' => (float) $item->unit_price,
                'quantity' => (float) $item->quantity,
                'line_total' => (float) $item->line_total,
            ])->values()->all(),
            'subtotal' => (float) $invoice->subtotal,
            'discount_type' => $invoice->discount_type,
            'discount_value' => (float) $invoice->discount_value,
            'discount_amount' => (float) $invoice->discount_amount,
            'tax_rate' => (float) $invoice->tax_rate,
            'tax_amount' => (float) $invoice->tax_amount,
            'round_off' => (float) $invoice->round_off,
            'total' => (float) $invoice->total,
            'payment_mode' => $invoice->payment_mode,
            'payment_status' => $invoice->payment_status,
            'notes' => $invoice->notes,
            'status' => $invoice->status,
            'void_reason' => $invoice->void_reason,
            'voided_at' => $invoice->voided_at?->toISOString(),
            'voided_by' => $invoice->voidedBy ? ['id' => $invoice->voidedBy->id, 'name' => $invoice->voidedBy->name] : null,
            'whatsapp_sent_at' => $invoice->whatsapp_sent_at?->toISOString(),
            'created_at' => $invoice->created_at->toISOString(),
        ];
    }

    // ---- end B2 ----

    /** @return array{from: string, to: string, status: string, payment_mode: string, sent: string, q: string} */
    protected function filters(Request $request): array
    {
        return [
            'from' => $request->query('from') ?: now()->startOfMonth()->toDateString(),
            'to' => $request->query('to') ?: now()->endOfMonth()->toDateString(),
            'status' => (string) $request->query('status', ''),
            'payment_mode' => (string) $request->query('payment_mode', ''),
            'sent' => (string) $request->query('sent', ''),
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    /** @param  array{from: string, to: string, status: string, payment_mode: string, sent: string, q: string}  $f */
    protected function filtered(array $f): Builder
    {
        return Invoice::query()
            ->whereBetween('invoice_date', [$f['from'], $f['to']])
            ->when($f['status'] !== '', fn ($q) => $q->where('status', $f['status']))
            ->when($f['payment_mode'] !== '', fn ($q) => $q->where('payment_mode', $f['payment_mode']))
            ->when($f['sent'] === 'sent', fn ($q) => $q->whereNotNull('whatsapp_sent_at'))
            ->when($f['sent'] === 'unsent', fn ($q) => $q->whereNull('whatsapp_sent_at'))
            ->when($f['q'] !== '', function ($q) use ($f) {
                $term = $f['q'];
                $digits = preg_replace('/\D+/', '', $term);
                $q->where(function ($w) use ($term, $digits) {
                    $w->where('invoice_number', 'like', "%{$term}%")
                        ->orWhereHas('customer', function ($c) use ($term, $digits) {
                            $c->where('name', 'like', "%{$term}%");
                            if ($digits !== '') {
                                $c->orWhere('phone', 'like', "%{$digits}%");
                            }
                        });
                });
            });
    }
}
