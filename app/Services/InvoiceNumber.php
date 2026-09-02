<?php

namespace App\Services;

use App\Models\Invoice;

final class InvoiceNumber
{
    /**
     * "{prefix}-{NNNN}" — next = max numeric suffix over all invoices + 1, zero-padded to 4.
     * Call inside the DB transaction that inserts the invoice; the unique index is the backstop.
     */
    public static function next(string $prefix): string
    {
        $max = Invoice::query()
            ->pluck('invoice_number')
            ->map(fn (string $number) => (int) (preg_match('/(\d+)$/', $number, $m) ? $m[1] : 0))
            ->max() ?? 0;

        return self::format($prefix, $max + 1);
    }

    public static function format(string $prefix, int $number): string
    {
        return $prefix.'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
