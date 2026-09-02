/** Date helpers. All inputs are "YYYY-MM-DD" or "YYYY-MM" strings (Asia/Kolkata calendar dates from the server). */

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const MONTHS_SHORT = MONTHS.map((m) => m.slice(0, 3));
const DAYS_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function pad(n: number): string {
    return n < 10 ? `0${n}` : String(n);
}

/** Today's date as YYYY-MM-DD in the browser's local timezone. */
export function todayIso(): string {
    const d = new Date();
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** Current month as YYYY-MM. */
export function currentMonth(): string {
    return todayIso().slice(0, 7);
}

export function parseIsoDate(iso: string): Date | null {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso ?? '');
    if (!m) return null;
    return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
}

/** "2026-09-02" → "2 Sep 2026" */
export function formatDate(iso: string | null | undefined): string {
    if (!iso) return '—';
    const d = parseIsoDate(iso);
    if (!d) return iso;
    return `${d.getDate()} ${MONTHS_SHORT[d.getMonth()]} ${d.getFullYear()}`;
}

/** "2026-09-02" → "Tue, 2 Sep 2026" */
export function formatDateLong(iso: string | null | undefined): string {
    if (!iso) return '—';
    const d = parseIsoDate(iso);
    if (!d) return iso;
    return `${DAYS_SHORT[d.getDay()]}, ${d.getDate()} ${MONTHS_SHORT[d.getMonth()]} ${d.getFullYear()}`;
}

/** ISO datetime → "2 Sep 2026, 4:35 pm" (browser local time). */
export function formatDateTime(iso: string | null | undefined): string {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    let h = d.getHours();
    const ampm = h >= 12 ? 'pm' : 'am';
    h = h % 12 || 12;
    return `${d.getDate()} ${MONTHS_SHORT[d.getMonth()]} ${d.getFullYear()}, ${h}:${pad(d.getMinutes())} ${ampm}`;
}

/** "2026-09" → "September 2026" */
export function formatMonth(ym: string | null | undefined): string {
    if (!ym) return '—';
    const m = /^(\d{4})-(\d{2})/.exec(ym);
    if (!m) return ym;
    return `${MONTHS[Number(m[2]) - 1]} ${m[1]}`;
}

export function addDays(iso: string, days: number): string {
    const d = parseIsoDate(iso) ?? new Date();
    d.setDate(d.getDate() + days);
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

export function addMonths(ym: string, months: number): string {
    const m = /^(\d{4})-(\d{2})/.exec(ym);
    if (!m) return ym;
    const d = new Date(Number(m[1]), Number(m[2]) - 1 + months, 1);
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
}

/** First and last day of a YYYY-MM month. */
export function monthRange(ym: string): { from: string; to: string } {
    const m = /^(\d{4})-(\d{2})/.exec(ym);
    if (!m) return { from: todayIso(), to: todayIso() };
    const y = Number(m[1]);
    const mo = Number(m[2]);
    const last = new Date(y, mo, 0).getDate();
    return { from: `${y}-${pad(mo)}-01`, to: `${y}-${pad(mo)}-${pad(last)}` };
}

/** Relative label for a date: "Today", "Yesterday", else formatDate. */
export function relativeDate(iso: string | null | undefined): string {
    if (!iso) return '—';
    if (iso === todayIso()) return 'Today';
    if (iso === addDays(todayIso(), -1)) return 'Yesterday';
    return formatDate(iso);
}
