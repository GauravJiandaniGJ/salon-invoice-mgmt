<?php

namespace App\Services\WhatsApp;

use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Support\Carbon;

class MessageTemplate
{
    public const PLACEHOLDERS = [
        '{greeting}', '{customer_name}', '{salon_name}', '{invoice_number}',
        '{total}', '{invoice_link}', '{date}', '{items}',
    ];

    public function render(string $template, Invoice $invoice): string
    {
        $invoice->loadMissing(['customer', 'items']);

        $replacements = [
            '{greeting}' => self::greeting(),
            '{customer_name}' => $invoice->customer->first_name ?: $invoice->customer->name,
            '{salon_name}' => (string) Setting::get('salon_name'),
            '{invoice_number}' => $invoice->invoice_number,
            '{total}' => self::formatAmount((float) $invoice->total),
            '{invoice_link}' => InvoiceLink::publicUrl($invoice),
            '{date}' => $invoice->invoice_date->format('j M Y'),
            '{items}' => self::itemsSummary($invoice->items->pluck('description')->all()),
            '{powered_by}' => self::poweredBy(),
        ];

        return self::withSignature(trim(strtr($template, $replacements)));
    }

    /**
     * The technology-partner line is always appended, never taken from the editable
     * template, so it cannot be removed or altered from Settings.
     */
    public static function withSignature(string $message): string
    {
        $signature = self::poweredBy();

        // Drop any "powered by ..." line the owner typed, so the credit cannot be
        // duplicated, reworded or reassigned to someone else.
        $lines = array_values(array_filter(
            preg_split('/\R/', $message) ?: [],
            fn (string $line) => ! preg_match('/^\s*[_*]*\s*powered\s+by\b/i', $line),
        ));

        $body = rtrim(implode("\n", $lines));

        return $body."\n_".$signature.'_';
    }

    /** "Powered by TodoIT · todoitservices.com" — technology partner line for messages. */
    public static function poweredBy(): string
    {
        $label = (string) config('salon.powered_by.label', 'Powered by TodoIT');
        $host = (string) parse_url((string) config('salon.powered_by.url', ''), PHP_URL_HOST);

        return $host !== '' ? $label.' · '.$host : $label;
    }

    /** Good morning (<12:00), Good afternoon (<17:00), Good evening — by app timezone (IST). */
    public static function greeting(?Carbon $now = null): string
    {
        $hour = (int) ($now ?? now())->format('G');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    /** @param  list<string>  $descriptions */
    public static function itemsSummary(array $descriptions, int $max = 3): string
    {
        $shown = array_slice($descriptions, 0, $max);
        $rest = count($descriptions) - count($shown);

        return implode(', ', $shown).($rest > 0 ? " +{$rest} more" : '');
    }

    /**
     * Indian digit grouping: 140000 → "1,40,000"; 1400.5 → "1,400.50"; whole numbers have no decimals.
     */
    public static function formatAmount(float $amount, bool $forceDecimals = false): string
    {
        $negative = $amount < 0;
        $amount = abs($amount);
        $isWhole = abs($amount - round($amount)) < 0.005;
        $decimals = ($isWhole && ! $forceDecimals) ? 0 : 2;

        [$int, $frac] = array_pad(explode('.', number_format($amount, $decimals, '.', '')), 2, null);

        if (strlen($int) > 3) {
            $last3 = substr($int, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($int, 0, -3));
            $int = $rest.','.$last3;
        }

        return ($negative ? '-' : '').$int.($frac !== null ? '.'.$frac : '');
    }
}
