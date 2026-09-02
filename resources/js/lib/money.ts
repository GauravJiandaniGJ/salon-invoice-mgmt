/** Indian-grouped rupee formatting: 140000 -> "₹1,40,000"; 1400.5 -> "₹1,400.50" */
const formatter = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2, minimumFractionDigits: 0 });

export function formatNumber(value: number | string | null | undefined): string {
    const n = Number(value ?? 0);
    if (Number.isNaN(n)) return '0';
    const isWhole = Math.abs(n - Math.round(n)) < 0.005;
    return isWhole ? formatter.format(Math.round(n)) : n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export function formatMoney(value: number | string | null | undefined): string {
    return '₹' + formatNumber(value);
}

export function round2(n: number): number {
    // Deterministic half-up rounding to the paisa; mirrors App\Services\InvoiceTotals::round2.
    return Math.round(n * 100 + (n >= 0 ? 1e-7 : -1e-7)) / 100;
}
