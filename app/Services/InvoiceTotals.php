<?php

namespace App\Services;

/**
 * Pure totals calculator. Mirrored in resources/js/lib/invoiceTotals.ts —
 * keep both in sync (docs/CONTRACT.md "Calculation rules").
 */
final class InvoiceTotals
{
    /**
     * @param  array<int, array{unit_price: float|int|string, quantity: float|int|string, description?: string, service_id?: int|null}>  $items
     * @return array{subtotal: float, discount_amount: float, tax_amount: float, round_off: float, total: float, items: array<int, array<string, mixed>>}
     */
    public static function calculate(array $items, ?string $discountType, float $discountValue, float $taxRate): array
    {
        $lines = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $lineTotal = self::round2((float) $item['unit_price'] * (float) $item['quantity']);
            $lines[] = [...$item, 'line_total' => $lineTotal];
            $subtotal += $lineTotal;
        }

        $subtotal = self::round2($subtotal);

        $discount = match ($discountType) {
            'percent' => self::round2($subtotal * $discountValue / 100),
            'flat' => self::round2($discountValue),
            default => 0.0,
        };
        $discount = max(0.0, min($discount, $subtotal));

        $taxable = self::round2($subtotal - $discount);
        $tax = self::round2($taxable * $taxRate / 100);
        $rawTotal = self::round2($taxable + $tax);
        $total = (float) round($rawTotal, 0);
        $roundOff = self::round2($total - $rawTotal);

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'round_off' => $roundOff,
            'total' => $total,
            'items' => $lines,
        ];
    }

    public static function round2(float $value): float
    {
        // Deterministic half-up rounding to the paisa, identical to resources/js/lib/invoiceTotals.ts.
        // The epsilon absorbs binary noise (e.g. 99.99 * 1.5 = 149.98499999…) so PHP 8.3, 8.4 and JS agree.
        return round($value * 100 + ($value >= 0 ? 1e-7 : -1e-7)) / 100;
    }
}
