<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\PdfRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PublicInvoiceController extends Controller
{
    public function show(string $code): View|Response
    {
        $invoice = $this->find($code);

        if (! $invoice) {
            return response()->view('public.invoice-not-found', [], 404);
        }

        return view('public.invoice', [
            'invoice' => $invoice,
            'salon' => PdfRenderer::salonDetails(),
            'pdfUrl' => route('public.invoice.pdf', $invoice->public_code),
        ]);
    }

    public function pdf(string $code, PdfRenderer $renderer): SymfonyResponse
    {
        $invoice = $this->find($code);

        if (! $invoice) {
            return response()->view('public.invoice-not-found', [], 404);
        }

        return $renderer->download($invoice);
    }

    private function find(string $code): ?Invoice
    {
        if (! preg_match('/^[A-Za-z0-9]{10}$/', $code)) {
            return null;
        }

        return Invoice::query()->where('public_code', $code)->with(['customer', 'items', 'staffMember'])->first();
    }
}
