<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\PdfRenderer;
use Illuminate\Console\Command;

class RegenerateInvoicePdf extends Command
{
    protected $signature = 'invoice:regenerate-pdf {id : Invoice id}';

    protected $description = 'Re-render and store the PDF for an invoice';

    public function handle(PdfRenderer $renderer): int
    {
        $invoice = Invoice::find($this->argument('id'));

        if (! $invoice) {
            $this->error('Invoice not found.');

            return self::FAILURE;
        }

        $path = $renderer->render($invoice);
        $this->info("Regenerated {$invoice->invoice_number} → {$path}");

        return self::SUCCESS;
    }
}
