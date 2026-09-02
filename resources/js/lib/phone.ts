/** Mirror of App\Support\PhoneNumber (docs/CONTRACT.md §Phone rules). */

/** Returns the normalised E.164-without-plus number ("919876543210") or null when invalid. */
export function normalisePhone(input: string | null | undefined): string | null {
    let digits = String(input ?? '').replace(/\D+/g, '');

    if (digits.length === 11 && digits.startsWith('0')) digits = digits.slice(1);

    if (digits.length === 10 && /^[6-9]/.test(digits)) return '91' + digits;
    if (digits.length === 12 && /^91[6-9]/.test(digits)) return digits;

    return null;
}

export function isValidPhone(input: string | null | undefined): boolean {
    return normalisePhone(input) !== null;
}

/** "919876543210" → "+91 98765 43210" */
export function displayPhone(normalised: string | null | undefined): string {
    const n = String(normalised ?? '');
    if (n.length !== 12) return n;
    return `+91 ${n.slice(2, 7)} ${n.slice(7)}`;
}

/** "919876543210" → "98XXXX3210" */
export function maskedPhone(normalised: string | null | undefined): string {
    const n = String(normalised ?? '');
    if (n.length !== 12) return n;
    const local = n.slice(2);
    return `${local.slice(0, 2)}XXXX${local.slice(-4)}`;
}
