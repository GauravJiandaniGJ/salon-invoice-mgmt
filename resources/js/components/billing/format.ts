const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/** "2026-09-02" (or ISO datetime) → "2 Sep 2026" */
export function formatDate(value: string | null | undefined): string {
    if (!value) return '—';
    const m = value.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (m) return `${parseInt(m[3], 10)} ${MONTHS[parseInt(m[2], 10) - 1]} ${m[1]}`;
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? value : `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
}

/** ISO datetime → "2 Sep 2026, 4:35 pm" (viewer's local time) */
export function formatDateTime(value: string | null | undefined): string {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    let h = d.getHours();
    const ampm = h >= 12 ? 'pm' : 'am';
    h = h % 12 || 12;
    const min = String(d.getMinutes()).padStart(2, '0');
    return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}, ${h}:${min} ${ampm}`;
}

export const PAYMENT_LABELS: Record<string, string> = { cash: 'Cash', upi: 'UPI', card: 'Card', other: 'Other' };

export function paymentLabel(mode: string | null | undefined): string {
    return PAYMENT_LABELS[mode ?? ''] ?? (mode ?? '—');
}
