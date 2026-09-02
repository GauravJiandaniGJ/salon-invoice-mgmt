<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Setting;
use App\Services\WhatsApp\MessageTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PdfRenderer
{
    public const DISK = 'local';

    /** Renders the invoice PDF, stores it on the local disk and records `pdf_path`. Returns the path. */
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['customer', 'items', 'staffMember']);

        $path = 'invoices/'.$invoice->invoice_number.'.pdf';

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'salon' => self::salonDetails(),
        ])->setPaper('a4', 'portrait');

        Storage::disk(self::DISK)->put($path, $pdf->output());

        if ($invoice->pdf_path !== $path) {
            $invoice->forceFill(['pdf_path' => $path])->save();
        }

        return $path;
    }

    /** Streams the stored PDF inline, regenerating it if the file is missing. */
    public function download(Invoice $invoice): Response
    {
        $disk = Storage::disk(self::DISK);

        if (! $invoice->pdf_path || ! $disk->exists($invoice->pdf_path)) {
            $this->render($invoice);
        }

        $filename = self::filename($invoice);

        return response($disk->get($invoice->pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /** Daily Statement PDF (view owned by the reports agent). Returns binary PDF. */
    public function dailyStatement(array $report): string
    {
        return Pdf::loadView('pdf.daily-statement', [
            'report' => $report,
            'salon' => self::salonDetails(),
        ])->setPaper('a4', 'portrait')->output();
    }

    /** "WowSalon-WS-0001.pdf" */
    public static function filename(Invoice $invoice): string
    {
        $name = preg_replace('/[^A-Za-z0-9]+/', '', (string) Setting::get('salon_name')) ?: 'Invoice';

        return $name.'-'.$invoice->invoice_number.'.pdf';
    }

    /**
     * Salon header details shared by the public page and the PDF.
     *
     * @return array{name: string, tagline: string, address: string, phone: string, footer_text: string, logo_src: ?string, logo_url: ?string}
     */
    public static function salonDetails(): array
    {
        $logoPath = (string) Setting::get('logo_path', '');
        $logoSrc = null;
        $logoUrl = null;

        if ($logoPath !== '' && Storage::disk('public')->exists($logoPath)) {
            $logoUrl = asset('storage/'.$logoPath);
            $mime = Storage::disk('public')->mimeType($logoPath) ?: 'image/png';
            $logoSrc = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($logoPath));
        } elseif (is_file($default = public_path('brand/wow-logo-transparent.png'))) {
            // Bundled salon logo until the owner uploads one in Settings.
            $logoUrl = asset('brand/wow-logo-transparent.png');
            $logoSrc = 'data:image/png;base64,'.base64_encode((string) file_get_contents($default));
        }

        return [
            'name' => (string) Setting::get('salon_name'),
            'tagline' => (string) Setting::get('salon_tagline', ''),
            'address' => (string) Setting::get('salon_address', ''),
            'phone' => (string) Setting::get('salon_phone', ''),
            'footer_text' => (string) Setting::get('footer_text', ''),
            'logo_src' => $logoSrc,
            'logo_url' => $logoUrl,
        ];
    }

    public static function money(float|string|null $amount): string
    {
        return '₹'.MessageTemplate::formatAmount((float) $amount);
    }
}
