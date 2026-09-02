<?php

namespace App\Services\WhatsApp;

use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Phase 2 driver: WhatsApp Cloud API (Meta Graph). Sends an approved utility
 * template with three body variables: customer first name, invoice number, link.
 * The free-text $message is not used — the Cloud API only allows templates for
 * business-initiated messages.
 */
class CloudApiSender implements WhatsAppSender
{
    public const GRAPH_URL = 'https://graph.facebook.com/v20.0';

    public function __construct(
        protected string $phoneNumberId,
        protected string $token,
        protected string $template = 'invoice_ready',
    ) {}

    public static function fromSettings(): self
    {
        return new self(
            (string) Setting::get('whatsapp_cloud_phone_id', ''),
            (string) Setting::get('whatsapp_cloud_token', ''),
            (string) Setting::get('whatsapp_cloud_template', 'invoice_ready') ?: 'invoice_ready',
        );
    }

    public function isConfigured(): bool
    {
        return $this->phoneNumberId !== '' && $this->token !== '';
    }

    /** @throws RuntimeException when not configured or the API rejects the message */
    public function send(Invoice $invoice, string $message): ?string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('WhatsApp Cloud API is not configured (phone number ID and token are required).');
        }

        $invoice->loadMissing('customer');

        $response = Http::withToken($this->token)
            ->timeout(10)
            ->acceptJson()
            ->post(self::GRAPH_URL.'/'.$this->phoneNumberId.'/messages', [
                'messaging_product' => 'whatsapp',
                'to' => $invoice->customer->phone,
                'type' => 'template',
                'template' => [
                    'name' => $this->template,
                    'language' => ['code' => 'en'],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $invoice->customer->first_name ?: $invoice->customer->name],
                            ['type' => 'text', 'text' => $invoice->invoice_number],
                            ['type' => 'text', 'text' => InvoiceLink::publicUrl($invoice)],
                        ],
                    ]],
                ],
            ]);

        if ($response->successful()) {
            return null;
        }

        $error = $response->json('error.message') ?: 'WhatsApp API error (HTTP '.$response->status().')';

        throw new RuntimeException($error);
    }
}
