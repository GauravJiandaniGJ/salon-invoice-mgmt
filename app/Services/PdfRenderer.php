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

    /** Downloads the stored PDF, regenerating it if the file is missing. */
    public function download(Invoice $invoice): Response
    {
        $disk = Storage::disk(self::DISK);

        if (! $invoice->pdf_path || ! $disk->exists($invoice->pdf_path)) {
            $this->render($invoice);
        }

        $filename = self::filename($invoice);

        return response($disk->get($invoice->pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /** Daily Statement PDF (view owned by the reports agent). Returns binary PDF. */
    public function dailyStatement(array $report): string
    {
        return $this->reportPdf('pdf.daily-statement', ['report' => $report]);
    }

    /** Renders any report Blade view (tables + inline CSS only) to a PDF binary. */
    public function reportPdf(string $view, array $data): string
    {
        return Pdf::loadView($view, $data + ['salon' => self::salonDetails()])->setPaper('a4', 'portrait')->output();
    }

    /**
     * A small base64 PNG for embedding in PDFs.
     *
     * The logo is inlined into every invoice, so a full-resolution file would add
     * ~1 MB per bill (≈22 GB a year at 60 bills a day). We downscale to print size
     * and cache the result next to the source.
     */
    protected static function embeddableLogo(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $cache = storage_path('app/private/logo-print-'.substr(md5($path.filemtime($path)), 0, 12).'.png');

        if (! is_file($cache)) {
            $bytes = self::downscalePng($path, 170);

            if ($bytes === null) {
                return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
            }

            @mkdir(dirname($cache), 0775, true);
            file_put_contents($cache, $bytes);
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($cache));
    }

    /** Resize an image to $width px wide, preserving transparency. Null when GD cannot read it. */
    protected static function downscalePng(string $path, int $width): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring((string) file_get_contents($path));

        if ($source === false) {
            return null;
        }

        $w = imagesx($source);
        $h = imagesy($source);

        if ($w <= $width) {
            imagedestroy($source);

            return (string) file_get_contents($path);
        }

        $height = (int) round($h * $width / $w);
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $w, $h);

        ob_start();
        imagepng($canvas, null, 9);
        $bytes = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return $bytes;
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
            $logoSrc = self::embeddableLogo(Storage::disk('public')->path($logoPath));
        } elseif (is_file($default = public_path('brand/wow-logo-print.png'))) {
            // Bundled salon logo until the owner uploads one in Settings.
            $logoUrl = asset('brand/wow-logo-transparent.png');
            $logoSrc = self::embeddableLogo($default);
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
