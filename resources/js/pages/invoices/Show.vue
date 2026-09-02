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
import type { BreadcrumbItem, InvoiceDetail, SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { AlertTriangle, Ban, Check, Copy, CopyPlus, Download, MessageCircle, Printer } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    invoice: InvoiceDetail;
    whatsapp_url: string | null;
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
const sentAt = ref<string | null>(props.invoice.whatsapp_sent_at);
const marking = ref(false);
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
            <div class="print-area relative flex-1 rounded-lg border bg-card p-6 shadow-sm">
                <div v-if="isVoid" class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-hidden" aria-hidden="true">
                    <span class="rotate-[-25deg] text-[7rem] font-black uppercase tracking-widest text-red-500/15 print:text-red-500/25">Void</span>
                </div>
                <div v-if="isVoid" class="mb-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                    <strong>VOID</strong> — {{ invoice.void_reason }}
                    <span class="block text-xs opacity-80">by {{ invoice.voided_by?.name ?? '—' }} · {{ formatDateTime(invoice.voided_at) }}</span>
                </div>

                <header class="flex flex-wrap items-start justify-between gap-3 border-b pb-4">
                    <div class="flex items-center gap-3">
                        <img v-if="page.props.salon.logo_url" :src="page.props.salon.logo_url" alt="" class="h-12 w-12 rounded object-cover" />
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
                        <p class="text-xs uppercase text-muted-foreground">Billed to</p>
                        <p class="font-medium">
                            <Link :href="`/customers/${invoice.customer.id}`" class="hover:underline">{{ invoice.customer.name }}</Link>
                        </p>
                        <p class="text-muted-foreground">{{ invoice.customer.phone_display }}</p>
                    </div>
                    <div class="sm:text-right">
                        <p class="text-xs uppercase text-muted-foreground">Details</p>
                        <p>Billed by <span class="font-medium">{{ invoice.billed_by.name }}</span></p>
                        <p v-if="invoice.staff_member">Served by <span class="font-medium">{{ invoice.staff_member.name }}</span></p>
                        <p>Payment: <span class="font-medium">{{ paymentLabel(invoice.payment_mode) }}</span> · {{ invoice.payment_status === 'paid' ? 'Paid' : 'Unpaid' }}</p>
                    </div>
                </section>

                <table class="w-full text-sm">
                    <thead class="border-b text-left text-xs uppercase text-muted-foreground">
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
                    <div class="flex justify-between"><dt class="text-muted-foreground">Subtotal</dt><dd class="tabular-nums">{{ formatMoney(invoice.subtotal) }}</dd></div>
                    <div v-if="invoice.discount_amount > 0" class="flex justify-between">
                        <dt class="text-muted-foreground">Discount<span v-if="invoice.discount_type === 'percent'"> ({{ Number(invoice.discount_value) }}%)</span></dt>
                        <dd class="tabular-nums">− {{ formatMoney(invoice.discount_amount) }}</dd>
                    </div>
                    <div v-if="invoice.tax_amount > 0" class="flex justify-between"><dt class="text-muted-foreground">GST {{ Number(invoice.tax_rate) }}%</dt><dd class="tabular-nums">{{ formatMoney(invoice.tax_amount) }}</dd></div>
                    <div v-if="Number(invoice.round_off) !== 0" class="flex justify-between"><dt class="text-muted-foreground">Round off</dt><dd class="tabular-nums">{{ invoice.round_off > 0 ? '+' : '−' }} {{ formatMoney(Math.abs(invoice.round_off)) }}</dd></div>
                    <div class="flex items-baseline justify-between border-t pt-2"><dt class="font-semibold">Total</dt><dd class="text-2xl font-bold tabular-nums">{{ formatMoney(invoice.total) }}</dd></div>
                </dl>

                <p v-if="invoice.notes" class="mt-4 rounded-md bg-muted p-2 text-xs text-muted-foreground print:hidden"><strong>Internal note:</strong> {{ invoice.notes }}</p>
                <p class="mt-6 text-center text-sm text-muted-foreground">Thank you for visiting {{ salonName }}!</p>
            </div>

            <!-- Actions -->
            <aside class="w-full space-y-3 print:hidden lg:w-80 lg:shrink-0">
                <div v-if="app_url_missing" class="flex gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                    <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                    <p>The public link uses <code>localhost</code>. Set the salon's public URL in <Link href="/settings" class="underline">Settings</Link> before sending to customers.</p>
                </div>

                <div class="rounded-lg border bg-card p-4">
                    <Button v-if="whatsapp_url && !isVoid" as-child size="lg" class="h-12 w-full bg-[#25D366] text-base text-white hover:bg-[#1ebe5b]">
                        <a :href="whatsapp_url" target="_blank" rel="noopener" @click="markSent"><MessageCircle /> Send on WhatsApp</a>
                    </Button>
                    <p v-else-if="isVoid" class="text-center text-sm text-muted-foreground">Void invoices can't be sent.</p>
                    <p v-else class="text-center text-sm text-muted-foreground">WhatsApp sending is not configured.</p>

                    <p class="mt-2 flex items-center justify-center gap-1 text-xs" :class="sentAt ? 'text-emerald-700 dark:text-emerald-400' : 'text-muted-foreground'">
                        <template v-if="sentAt"><Check class="h-3.5 w-3.5" /> Sent {{ formatDateTime(sentAt) }}</template>
                        <template v-else>Not sent yet</template>
                    </p>
                    <p v-if="!isVoid" class="mt-1 text-center text-[11px] text-muted-foreground">Opens WhatsApp Web with the message ready — press Enter there to send.</p>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <Button variant="outline" class="h-10" @click="copyLink"><Copy /> {{ copied ? 'Copied!' : 'Copy link' }}</Button>
                    <Button as-child variant="outline" class="h-10"><a :href="pdf_url" target="_blank" rel="noopener"><Download /> PDF</a></Button>
                    <Button variant="outline" class="h-10" @click="printInvoice"><Printer /> Print</Button>
                    <Button as-child variant="outline" class="h-10"><Link :href="`/bills/new?duplicate=${invoice.id}`"><CopyPlus /> Duplicate</Link></Button>
                </div>

                <div class="rounded-lg border bg-card p-3 text-xs text-muted-foreground">
                    <p class="mb-1 font-medium text-foreground">Public link</p>
                    <a :href="public_url" target="_blank" rel="noopener" class="break-all underline underline-offset-2">{{ public_url }}</a>
                </div>

                <details class="rounded-lg border bg-card p-3 text-xs">
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
                    <DialogDescription>The invoice stays in the records with a VOID mark and is excluded from earnings. This cannot be undone.</DialogDescription>
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
