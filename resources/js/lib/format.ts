import type { PaymentMode, PaymentStatus } from '@/types';

export const PAYMENT_MODES: PaymentMode[] = ['cash', 'upi', 'card', 'other'];

export const PAYMENT_MODE_LABELS: Record<PaymentMode, string> = {
    cash: 'Cash',
    upi: 'UPI',
    card: 'Card',
    other: 'Other',
};

export function paymentModeLabel(mode: PaymentMode | string | null | undefined): string {
    if (!mode) return '—';
    return PAYMENT_MODE_LABELS[mode as PaymentMode] ?? mode;
}

export function paymentStatusLabel(status: PaymentStatus | string | null | undefined): string {
    if (status === 'unpaid') return 'Unpaid';
    if (status === 'paid') return 'Paid';
    return status ?? '—';
}

export function pluralise(count: number, singular: string, plural = `${singular}s`): string {
    return `${count} ${count === 1 ? singular : plural}`;
}

export function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase() ?? '')
        .join('');
}

/** Turn `{ cash: 100, upi: 50, ... }` into ordered rows for tables. */
export function byModeRows(byMode: Partial<Record<PaymentMode, number>> | null | undefined): { mode: PaymentMode; label: string; amount: number }[] {
    return PAYMENT_MODES.map((mode) => ({ mode, label: PAYMENT_MODE_LABELS[mode], amount: Number(byMode?.[mode] ?? 0) }));
}
