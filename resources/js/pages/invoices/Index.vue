<script setup lang="ts">
import { formatDate, paymentLabel } from '@/components/billing/format';
import PaginationLinks from '@/components/billing/PaginationLinks.vue';
import StatusBadge from '@/components/billing/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/money';
import type { BreadcrumbItem, InvoiceFilters, InvoiceRow, Paginated, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, Download, FilePlus2, Pencil, Search, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps<{
    filters: InvoiceFilters;
    invoices: Paginated<InvoiceRow>;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Invoices', href: '/invoices' }];
const page = usePage<SharedData>();
const isOwner = computed(() => page.props.auth.user?.role === 'owner');

const filters = reactive<InvoiceFilters>({ ...props.filters });
const loading = ref(false);

const apply = () => {
    router.get('/invoices', { ...filters }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
};

let qTimer: ReturnType<typeof setTimeout> | null = null;
watch(
    () => filters.q,
    () => {
        if (qTimer) clearTimeout(qTimer);
        qTimer = setTimeout(apply, 350);
    },
);
watch(() => [filters.from, filters.to, filters.status, filters.payment_mode, filters.sent], apply);

const reset = () => {
    Object.assign(filters, { from: props.filters.from, to: props.filters.to, status: '', payment_mode: '', sent: '', q: '' });
};

const exportUrl = computed(() => {
    const qs = new URLSearchParams(Object.entries(filters).filter(([, v]) => v !== '') as [string, string][]);
    return `/invoices/export.csv?${qs.toString()}`;
});
</script>

<template>
    <Head title="Invoices" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold">Invoices</h1>
                <div class="flex gap-2">
                    <Button v-if="isOwner" as-child variant="outline">
                        <a :href="exportUrl"><Download /> Export CSV</a>
                    </Button>
                    <Button as-child>
                        <Link href="/bills/new"><FilePlus2 /> New Bill</Link>
                    </Button>
                </div>
            </div>

            <div class="grid gap-2 rounded-xl border bg-card shadow-sm p-3 md:grid-cols-[1fr_auto_auto_auto_auto_auto_auto]">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.q" placeholder="Search customer, phone or invoice no." class="h-10 pl-9" />
                </div>
                <Input v-model="filters.from" type="date" class="h-10" aria-label="From date" />
                <Input v-model="filters.to" type="date" class="h-10" aria-label="To date" />
                <select v-model="filters.status" class="h-10 rounded-md border border-input bg-background px-3 text-sm" aria-label="Status">
                    <option value="">All statuses</option>
                    <option value="issued">Issued</option>
                    <option value="void">Void</option>
                </select>
                <select v-model="filters.payment_mode" class="h-10 rounded-md border border-input bg-background px-3 text-sm" aria-label="Payment mode">
                    <option value="">All payments</option>
                    <option value="cash">Cash</option>
                    <option value="upi">UPI</option>
                    <option value="card">Card</option>
                    <option value="other">Other</option>
                </select>
                <select v-model="filters.sent" class="h-10 rounded-md border border-input bg-background px-3 text-sm" aria-label="WhatsApp sent">
                    <option value="">Sent & unsent</option>
                    <option value="sent">Sent</option>
                    <option value="unsent">Not sent</option>
                </select>
                <Button variant="ghost" class="h-10" @click="reset"><X /> Reset</Button>
            </div>

            <div class="overflow-x-auto rounded-xl border bg-card shadow-sm" :class="{ 'opacity-60': loading }">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">Invoice</th>
                            <th class="px-3 py-2 font-medium">Date</th>
                            <th class="px-3 py-2 font-medium">Customer</th>
                            <th class="px-3 py-2 font-medium">Items</th>
                            <th class="px-3 py-2 text-right font-medium">Total</th>
                            <th class="px-3 py-2 font-medium">Payment</th>
                            <th class="px-3 py-2 text-center font-medium">Sent</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="invoices.data.length === 0">
                            <td colspan="8" class="px-3 py-10 text-center text-muted-foreground">No invoices match these filters.</td>
                        </tr>
                        <tr v-for="inv in invoices.data" :key="inv.id" class="border-t hover:bg-accent/40" :class="{ 'text-muted-foreground': inv.status === 'void' }">
                            <td class="px-3 py-2 font-medium">
                                <Link :href="`/invoices/${inv.id}`" class="hover:underline">{{ inv.invoice_number }}</Link>
                                <StatusBadge v-if="inv.status === 'void' || inv.payment_status === 'unpaid'" :status="inv.status" :payment-status="inv.payment_status" class="ml-1" />
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">{{ formatDate(inv.invoice_date) }}</td>
                            <td class="px-3 py-2">
                                <Link :href="`/customers/${inv.customer.id}`" class="hover:underline">{{ inv.customer.name }}</Link>
                                <span class="block text-xs text-muted-foreground">{{ inv.customer.phone_display }}</span>
                            </td>
                            <td class="max-w-[280px] truncate px-3 py-2" :title="inv.items_summary">{{ inv.items_summary }}</td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums" :class="{ 'line-through': inv.status === 'void' }">{{ formatMoney(inv.total) }}</td>
                            <td class="px-3 py-2">{{ paymentLabel(inv.payment_mode) }}</td>
                            <td class="px-3 py-2 text-center">
                                <Check v-if="inv.whatsapp_sent_at" class="mx-auto h-4 w-4 text-emerald-600" aria-label="Sent" />
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-right">
                                <Button v-if="inv.status === 'issued'" as-child size="sm" variant="ghost" class="h-8 w-8 p-0" title="Edit bill">
                                    <Link :href="`/invoices/${inv.id}/edit`" aria-label="Edit bill"><Pencil /></Link>
                                </Button>
                                <Button as-child size="sm" :variant="inv.whatsapp_sent_at || inv.status === 'void' ? 'ghost' : 'outline'">
                                    <Link :href="`/invoices/${inv.id}`">{{ inv.whatsapp_sent_at || inv.status === 'void' ? 'View' : 'Send' }}</Link>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <PaginationLinks :paginator="invoices" />
        </div>
    </AppLayout>
</template>
