<?php

namespace App\Services\WhatsApp;

use App\Models\Invoice;

interface WhatsAppSender
{
    /**
     * Returns a URL for the receptionist to open (phase 1, wa.me) or null when
     * the message was sent server-side (phase 2, Cloud API).
     */
    public function send(Invoice $invoice, string $message): ?string;
}
