import type { DiscountType, Totals } from '@/types';

/** Mirror of App\Services\InvoiceTotals — keep the two in sync (docs/CONTRACT.md §Calculation rules). */

export function round2(n: number): number {
    return Math.round((n + Number.EPSILON) * 100) / 100;
}

export interface TotalsLineInput {
    unit_price: number | string | null;
    quantity: number | string | null;
}

export interface TotalsResult extends Totals {
    line_totals: number[];
    taxable: number;
}

const num = (v: number | string | null | undefined): number => {
    const n = typeof v === 'number' ? v : parseFloat(String(v ?? ''));
    return Number.isFinite(n) ? n : 0;
};

export function lineTotal(line: TotalsLineInput): number {
    return round2(num(line.unit_price) * num(line.quantity));
}

export function calculateTotals(
    items: TotalsLineInput[],
    discountType: DiscountType | null,
    discountValue: number | string | null,
    taxRate: number | string | null,
): TotalsResult {
    const line_totals = items.map(lineTotal);
    const subtotal = round2(line_totals.reduce((sum, t) => sum + t, 0));

    let discount_amount = 0;
    const value = num(discountValue);
    if (discountType === 'percent') discount_amount = round2((subtotal * value) / 100);
    else if (discountType === 'flat') discount_amount = round2(value);
    discount_amount = Math.min(Math.max(discount_amount, 0), subtotal);

    const taxable = round2(subtotal - discount_amount);
    const tax_amount = round2((taxable * num(taxRate)) / 100);
    const raw_total = round2(taxable + tax_amount);
    const total = Math.round(raw_total + 1e-9);
    const round_off = round2(total - raw_total);

    return { subtotal, discount_amount, tax_amount, round_off, total, line_totals, taxable };
}
