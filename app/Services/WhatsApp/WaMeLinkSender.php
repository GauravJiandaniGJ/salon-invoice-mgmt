<?php

namespace App\Services\WhatsApp;

use App\Models\Invoice;

class WaMeLinkSender implements WhatsAppSender
{
    public function send(Invoice $invoice, string $message): ?string
    {
        // Phone is stored normalised (E.164 without "+"), so the link never has spaces or a leading 0.
        return 'https://wa.me/'.$invoice->customer->phone.'?text='.rawurlencode($message);
    }
}
