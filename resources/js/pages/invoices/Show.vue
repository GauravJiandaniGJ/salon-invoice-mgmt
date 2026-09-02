<script setup lang="ts">
import { formatDate, formatDateTime, paymentLabel } from '@/components/billing/format';
import { postJson } from '@/components/billing/http';
import StatusBadge from '@/components/billing/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/money';
import type { BreadcrumbItem, InvoiceDetail, SendResponse, SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { AlertTriangle, Ban, Check, Copy, CopyPlus, Download, Pencil, Printer } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    invoice: InvoiceDetail;
    whatsapp_web_url: string | null;
    whatsapp_mobile_url: string | null;
    whatsapp_mode: 'link' | 'cloud';
    whatsapp_message: string;
    public_url: string;
    pdf_url: string;
    app_url_missing: boolean;
    can_void: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Invoices', href: '/invoices' },
    { title: props.invoice.invoice_number, href: `/invoices/${props.invoice.id}` },
];

const page = usePage<SharedData>();
const salonName = computed(() => page.props.salon.name);

// ----- send on WhatsApp -----
// Desktop opens WhatsApp Web directly (the wa.me interstitial mangles emoji); phones use wa.me → app.
const isMobileDevice = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
const linkUrl = computed(() => (isMobileDevice ? props.whatsapp_mobile_url : props.whatsapp_web_url));
const sentAt = ref<string | null>(props.invoice.whatsapp_sent_at);
const marking = ref(false);
const sending = ref(false);
const sendError = ref<string | null>(null);
const fallbackUrl = ref<string | null>(null);

const openChat = (url: string | null) => {
    if (!url) return;
    const win = window.open(url, '_blank', 'noopener');
    if (!win) fallbackUrl.value = url; // popup blocked → show a plain link
};

const send = async () => {
    if (sending.value) return;
    sendError.value = null;
    fallbackUrl.value = null;

    if (props.whatsapp_mode !== 'cloud') {
        openChat(linkUrl.value);
        await markSent();
        return;
    }

    sending.value = true;
    try {
        const res = await postJson<SendResponse>(`/invoices/${props.invoice.id}/send`);
        if (res.sent) {
            sentAt.value = res.whatsapp_sent_at;
        } else {
            sendError.value = res.error ?? 'Automatic sending is not available right now.';
            openChat(res.fallback_url ?? linkUrl.value);
            await markSent();
        }
    } catch {
        sendError.value = 'Could not reach the server. Opening WhatsApp instead.';
        openChat(linkUrl.value);
        await markSent();
    } finally {
        sending.value = false;
    }
};

const markSent = async () => {
    if (marking.value) return;
    marking.value = true;
    const optimistic = new Date().toISOString();
    const previous = sentAt.value;
    sentAt.value = sentAt.value ?? optimistic;
    try {
        const res = await postJson<{ whatsapp_sent_at: string }>(`/invoices/${props.invoice.id}/mark-sent`);
        sentAt.value = res.whatsapp_sent_at;
    } catch {
        sentAt.value = previous; // link still opened; just don't claim it was recorded
    } finally {
        marking.value = false;
    }
};

// ----- copy link -----
const copied = ref(false);
const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(props.public_url);
    } catch {
        const ta = document.createElement('textarea');
        ta.value = props.public_url;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
    }
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};

// ----- void -----
const voidOpen = ref(false);
const voidForm = useForm({ reason: '' });
const submitVoid = () => {
    voidForm.post(`/invoices/${props.invoice.id}/void`, {
        preserveScroll: true,
        onSuccess: () => {
            voidOpen.value = false;
            voidForm.reset();
        },
    });
};

const isVoid = computed(() => props.invoice.status === 'void');
const printInvoice = () => window.print();
</script>

<template>
    <Head :title="`Invoice ${invoice.invoice_number}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4 lg:flex-row">
            <!-- Preview -->
            <div class="print-area relative flex-1 rounded-xl border bg-card p-6 shadow-sm">
                <div v-if="isVoid" class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-hidden" aria-hidden="true">
                    <span class="rotate-[-25deg] text-[7rem] font-black uppercase tracking-widest text-red-500/15 print:text-red-500/25">Void</span>
                </div>
                <div
                    v-if="isVoid"
                    class="mb-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
                >
                    <strong>VOID</strong> — {{ invoice.void_reason }}
                    <span class="block text-xs opacity-80">by {{ invoice.voided_by?.name ?? '—' }} · {{ formatDateTime(invoice.voided_at) }}</span>
                </div>

                <header class="flex flex-wrap items-start justify-between gap-3 border-b pb-4">
                    <div class="flex items-center gap-3">
                        <img v-if="page.props.salon.logo_url" :src="page.props.salon.logo_url" alt="" class="h-14 w-auto rounded-md" />
                        <div>
                            <h1 class="text-xl font-bold">{{ salonName }}</h1>
                            <p class="text-xs text-muted-foreground">Invoice</p>
                        </div>
                    </div>
                    <div class="text-right text-sm">
                        <p class="text-lg font-semibold">{{ invoice.invoice_number }}</p>
                        <p class="text-muted-foreground">{{ formatDate(invoice.invoice_date) }}</p>
                        <StatusBadge :status="invoice.status" :payment-status="invoice.payment_status" class="mt-1" />
                    </div>
                </header>

                <section class="grid gap-3 py-4 text-sm sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Billed to</p>
                        <p class="font-medium">
                            <Link :href="`/customers/${invoice.customer.id}`" class="hover:underline">{{ invoice.customer.name }}</Link>
                        </p>
                        <p class="text-muted-foreground">{{ invoice.customer.phone_display }}</p>
                    </div>
                    <div class="sm:text-right">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Details</p>
                        <p>
                            Billed by <span class="font-medium">{{ invoice.billed_by.name }}</span>
                        </p>
                        <p v-if="invoice.staff_member">
                            Served by <span class="font-medium">{{ invoice.staff_member.name }}</span>
                        </p>
                        <p>
                            Payment: <span class="font-medium">{{ paymentLabel(invoice.payment_mode) }}</span> ·
                            {{ invoice.payment_status === 'paid' ? 'Paid' : 'Unpaid' }}
                        </p>
                    </div>
                </section>

                <table class="w-full text-sm">
                    <thead class="border-b text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="py-2 font-medium">Description</th>
                            <th class="py-2 text-right font-medium">Qty</th>
                            <th class="py-2 text-right font-medium">Rate</th>
                            <th class="py-2 text-right font-medium">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in invoice.items" :key="item.id" class="border-b border-dashed">
                            <td class="py-2">{{ item.description }}</td>
                            <td class="py-2 text-right tabular-nums">{{ Number(item.quantity) }}</td>
                            <td class="py-2 text-right tabular-nums">{{ formatMoney(item.unit_price) }}</td>
                            <td class="py-2 text-right font-medium tabular-nums">{{ formatMoney(item.line_total) }}</td>
                        </tr>
                    </tbody>
                </table>

                <dl class="ml-auto mt-4 w-full max-w-xs space-y-1 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Subtotal</dt>
                        <dd class="tabular-nums">{{ formatMoney(invoice.subtotal) }}</dd>
                    </div>
                    <div v-if="invoice.discount_amount > 0" class="flex justify-between">
                        <dt class="text-muted-foreground">
                            Discount<span v-if="invoice.discount_type === 'percent'"> ({{ Number(invoice.discount_value) }}%)</span>
                        </dt>
                        <dd class="tabular-nums">− {{ formatMoney(invoice.discount_amount) }}</dd>
                    </div>
                    <div v-if="invoice.tax_amount > 0" class="flex justify-between">
                        <dt class="text-muted-foreground">GST {{ Number(invoice.tax_rate) }}%</dt>
                        <dd class="tabular-nums">{{ formatMoney(invoice.tax_amount) }}</dd>
                    </div>
                    <div v-if="Number(invoice.round_off) !== 0" class="flex justify-between">
                        <dt class="text-muted-foreground">Round off</dt>
                        <dd class="tabular-nums">{{ invoice.round_off > 0 ? '+' : '−' }} {{ formatMoney(Math.abs(invoice.round_off)) }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between border-t pt-2">
                        <dt class="font-semibold">Total</dt>
                        <dd class="text-2xl font-bold tabular-nums">{{ formatMoney(invoice.total) }}</dd>
                    </div>
                </dl>

                <p v-if="invoice.notes" class="mt-4 rounded-md bg-muted p-2 text-xs text-muted-foreground print:hidden">
                    <strong>Internal note:</strong> {{ invoice.notes }}
                </p>
                <p class="mt-6 text-center text-sm text-muted-foreground">Thank you for visiting {{ salonName }}!</p>
            </div>

            <!-- Actions -->
            <aside class="w-full space-y-3 lg:w-80 lg:shrink-0 print:hidden">
                <div
                    v-if="app_url_missing"
                    class="flex gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
                >
                    <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                    <p>
                        The public link uses <code>localhost</code>. Set the salon's public URL in
                        <Link href="/settings" class="underline">Settings</Link> before sending to customers.
                    </p>
                </div>

                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <button
                        v-if="linkUrl && !isVoid"
                        type="button"
                        class="group inline-flex h-12 w-full items-center justify-center gap-3 rounded-full border border-[#111] bg-[#25D366] px-6 text-base font-semibold text-[#111] transition-all duration-300 ease-out hover:bg-[#111] hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#25D366] focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-80 dark:border-[#25D366] dark:hover:border-white"
                        :disabled="sending"
                        @click="send"
                    >
                        <span>{{ sending ? 'Sending…' : 'Send on WhatsApp' }}</span>
                        <LoaderCircle v-if="sending" class="h-5 w-5 animate-spin" />
                        <svg
                            v-else
                            class="h-5 w-5 transition-transform duration-300 ease-out group-hover:translate-x-1 group-hover:rotate-[8deg]"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.39-1.47-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35M12.05 21.79h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.44-9.88 9.9-9.88 2.64 0 5.12 1.03 6.99 2.9a9.82 9.82 0 0 1 2.89 6.99c0 5.45-4.44 9.88-9.89 9.88m8.41-18.3A11.82 11.82 0 0 0 12.05 0C5.5 0 .16 5.33.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.9 11.9 0 0 0 5.68 1.45h.01c6.55 0 11.89-5.33 11.89-11.89 0-3.18-1.24-6.16-3.48-8.41"
                            />
                        </svg>
                    </button>
                    <p v-else-if="isVoid" class="text-center text-sm text-muted-foreground">Void invoices can't be sent.</p>
                    <p v-else class="text-center text-sm text-muted-foreground">WhatsApp sending is not configured.</p>

                    <p
                        class="mt-2 flex items-center justify-center gap-1 text-xs"
                        :class="sentAt ? 'text-emerald-700 dark:text-emerald-400' : 'text-muted-foreground'"
                    >
                        <template v-if="sentAt"><Check class="h-3.5 w-3.5" /> Sent {{ formatDateTime(sentAt) }}</template>
                        <template v-else>Not sent yet</template>
                    </p>
                    <p v-if="!isVoid" class="mt-1 text-center text-[11px] text-muted-foreground">
                        <template v-if="whatsapp_mode === 'cloud'">Sent automatically from the salon's WhatsApp Business number.</template>
                        <template v-else-if="isMobileDevice">Opens the chat in the WhatsApp app — press Send there.</template>
                        <template v-else>Opens the chat in WhatsApp Web — press Enter to send.</template>
                    </p>
                    <p
                        v-if="sendError"
                        class="mt-2 rounded-md bg-amber-50 p-2 text-center text-[11px] text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                    >
                        {{ sendError }}
                    </p>
                    <a
                        v-if="fallbackUrl"
                        :href="fallbackUrl"
                        target="_blank"
                        rel="noopener"
                        class="mt-2 block text-center text-xs underline underline-offset-2"
                    >
                        Pop-up blocked — tap here to open WhatsApp
                    </a>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <Button variant="outline" class="h-10" @click="copyLink"><Copy /> {{ copied ? 'Copied!' : 'Copy link' }}</Button>
                    <Button as-child variant="outline" class="h-10"
                        ><a :href="pdf_url" target="_blank" rel="noopener"><Download /> PDF</a></Button
                    >
                    <Button variant="outline" class="h-10" @click="printInvoice"><Printer /> Print</Button>
                    <Button as-child variant="outline" class="h-10"
                        ><Link :href="`/bills/new?duplicate=${invoice.id}`"><CopyPlus /> Duplicate</Link></Button
                    >
                    <Button v-if="invoice.status === 'issued'" as-child variant="outline" class="h-10"
                        ><Link :href="`/invoices/${invoice.id}/edit`"><Pencil /> Edit</Link></Button
                    >
                </div>

                <div class="rounded-xl border bg-card p-3 text-xs text-muted-foreground shadow-sm">
                    <p class="mb-1 font-medium text-foreground">Public link</p>
                    <a :href="public_url" target="_blank" rel="noopener" class="break-all underline underline-offset-2">{{ public_url }}</a>
                </div>

                <details class="rounded-xl border bg-card p-3 text-xs shadow-sm">
                    <summary class="cursor-pointer font-medium">Message preview</summary>
                    <pre class="mt-2 whitespace-pre-wrap font-sans text-muted-foreground">{{ whatsapp_message }}</pre>
                </details>

                <Button v-if="can_void" variant="destructive" class="h-10 w-full" @click="voidOpen = true"><Ban /> Void invoice</Button>
            </aside>
        </div>

        <Dialog v-model:open="voidOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Void {{ invoice.invoice_number }}?</DialogTitle>
                    <DialogDescription
                        >The invoice stays in the records with a VOID mark and is excluded from earnings. This cannot be undone.</DialogDescription
                    >
                </DialogHeader>
                <form class="grid gap-2" @submit.prevent="submitVoid">
                    <Label for="void-reason">Reason</Label>
                    <Input id="void-reason" v-model="voidForm.reason" placeholder="e.g. Billed to the wrong customer" autofocus maxlength="200" />
                    <p v-if="voidForm.errors.reason" class="text-xs text-destructive">{{ voidForm.errors.reason }}</p>
                    <DialogFooter class="mt-2">
                        <Button type="button" variant="ghost" @click="voidOpen = false">Cancel</Button>
                        <Button type="submit" variant="destructive" :disabled="voidForm.processing || !voidForm.reason.trim()">Void invoice</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    .print-area,
    .print-area * {
        visibility: visible;
    }
    .print-area {
        position: absolute;
        inset: 0;
        border: 0;
        box-shadow: none;
    }
}
</style>
