<?php

namespace App\Services\WhatsApp;

use App\Models\Invoice;
use App\Models\Setting;

/** Builds the customer-facing invoice URL from the `app_url` setting. */
class InvoiceLink
{
    public static function publicUrl(Invoice $invoice): string
    {
        return rtrim((string) Setting::get('app_url', config('app.url')), '/').'/i/'.$invoice->public_code;
    }

    /** True when app_url is unset or still points at localhost — the WhatsApp link would be wrong. */
    public static function appUrlMissing(): bool
    {
        $url = trim((string) Setting::get('app_url', ''));

        return $url === '' || str_contains($url, 'localhost') || str_contains($url, '127.0.0.1');
    }
}
