<script setup lang="ts">
import DateRangePicker from '@/components/DateRangePicker.vue';
import EmptyState from '@/components/EmptyState.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { byModeRows, paymentModeLabel } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type DailyReport, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Download, FileText, Printer } from 'lucide-vue-next';
import { computed } from 'vue';
import ReportTabs from './ReportTabs.vue';

const props = defineProps<{
    report: DailyReport;
    can_pick_date: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: '/reports/daily' },
    { title: 'Daily statement', href: '/reports/daily' },
];

const goTo = (range: { from: string; to: string }) => router.get('/reports/daily', range, { preserveState: true, preserveScroll: true });
const page = usePage<SharedData>();
const isOwner = computed(() => page.props.auth.user?.role === 'owner');
const query = computed(() => `from=${props.report.from}&to=${props.report.to}`);
const isRange = computed(() => props.report.from !== props.report.to);

const earningsRows = computed(() => byModeRows(props.report.earnings.by_mode));
const expenseRows = computed(() => byModeRows(props.report.expenses.by_mode));
const printPage = () => window.print();
</script>

<template>
    <Head :title="`Daily statement · ${report.date_label}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 print:p-0">
            <ReportTabs active="daily" />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">{{ isRange ? 'Statement' : 'Daily statement' }}</h1>
                    <p class="text-sm text-muted-foreground">{{ report.date_label }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-2 print:hidden">
                    <DateRangePicker v-if="can_pick_date" :from="report.from" :to="report.to" @change="goTo" />
                    <Button variant="outline" size="sm" @click="printPage"><Printer /> Print</Button>
                    <Button as-child variant="outline" size="sm">
                        <a :href="`/reports/daily/pdf?${query}`"><Download /> PDF</a>
                    </Button>
                    <Button as-child variant="ghost" size="sm">
                        <a :href="`/reports/daily.csv?${query}`">CSV</a>
                    </Button>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Invoices" :value="String(report.invoices_count)" :hint="`${report.customers_served} customers served`" />
                <StatCard label="Earnings" :value="formatMoney(report.earnings.total)" tone="positive" />
                <StatCard label="Expenses" :value="formatMoney(report.expenses.total)" tone="negative" />
                <StatCard
                    label="Net"
                    :value="formatMoney(report.net)"
                    :tone="report.net < 0 ? 'negative' : 'default'"
                    :hint="`Cash in hand ${formatMoney(report.cash_in_hand)}`"
                />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <section class="rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-sm font-semibold">Earnings by payment mode</h2>
                    <table class="mt-2 w-full text-sm">
                        <tbody>
                            <tr v-for="row in earningsRows" :key="row.mode" class="border-t">
                                <td class="py-1.5">{{ row.label }}</td>
                                <td class="py-1.5 text-right tabular-nums">{{ formatMoney(row.amount) }}</td>
                            </tr>
                            <tr class="border-t font-semibold">
                                <td class="py-1.5">Total</td>
                                <td class="py-1.5 text-right tabular-nums">{{ formatMoney(report.earnings.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-sm font-semibold">Expenses by payment mode</h2>
                    <table class="mt-2 w-full text-sm">
                        <tbody>
                            <tr v-for="row in expenseRows" :key="row.mode" class="border-t">
                                <td class="py-1.5">{{ row.label }}</td>
                                <td class="py-1.5 text-right tabular-nums">{{ formatMoney(row.amount) }}</td>
                            </tr>
                            <tr class="border-t font-semibold">
                                <td class="py-1.5">Total</td>
                                <td class="py-1.5 text-right tabular-nums">{{ formatMoney(report.expenses.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>

            <section class="rounded-xl border bg-card shadow-sm">
                <header class="border-b px-4 py-3">
                    <h2 class="text-sm font-semibold">Invoices ({{ report.invoices.length }})</h2>
                </header>
                <EmptyState v-if="report.invoices.length === 0" title="No invoices on this day" :icon="FileText" class="m-4" />
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Invoice</th>
                                <th class="px-4 py-2 font-medium">Customer</th>
                                <th class="hidden px-4 py-2 font-medium sm:table-cell">Staff</th>
                                <th class="px-4 py-2 font-medium">Mode</th>
                                <th class="px-4 py-2 text-right font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="inv in report.invoices" :key="inv.id" class="border-t">
                                <td class="px-4 py-1.5">
                                    <Link :href="`/invoices/${inv.id}`" class="font-medium hover:underline">{{ inv.invoice_number }}</Link>
                                </td>
                                <td class="px-4 py-1.5">{{ inv.customer_name }}</td>
                                <td class="hidden px-4 py-1.5 text-muted-foreground sm:table-cell">{{ inv.staff_member ?? '—' }}</td>
                                <td class="px-4 py-1.5">{{ paymentModeLabel(inv.payment_mode) }}</td>
                                <td class="px-4 py-1.5 text-right tabular-nums">{{ formatMoney(inv.total) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t bg-muted/30 font-semibold">
                                <td colspan="4" class="px-4 py-2">Total earnings</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(report.earnings.total) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <section v-if="report.voided.length" class="rounded-xl border border-dashed bg-card">
                <header class="border-b px-4 py-3">
                    <h2 class="text-sm font-semibold text-muted-foreground">Voided ({{ report.voided.length }}) — not included in totals</h2>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-muted-foreground">
                        <tbody>
                            <tr v-for="inv in report.voided" :key="inv.id" class="border-t">
                                <td class="px-4 py-1.5">
                                    <Link :href="`/invoices/${inv.id}`" class="hover:underline">{{ inv.invoice_number }}</Link>
                                </td>
                                <td class="px-4 py-1.5">{{ inv.customer_name }}</td>
                                <td class="px-4 py-1.5 italic">{{ inv.void_reason }}</td>
                                <td class="px-4 py-1.5 text-right tabular-nums line-through">{{ formatMoney(inv.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-xl border bg-card shadow-sm">
                <header class="border-b px-4 py-3">
                    <h2 class="text-sm font-semibold">Expenses ({{ report.expense_lines.length }})</h2>
                </header>
                <EmptyState v-if="report.expense_lines.length === 0" title="No expenses in this period" class="m-4" />
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Category</th>
                                <th class="px-4 py-2 font-medium">Description</th>
                                <th class="px-4 py-2 font-medium">Mode</th>
                                <th class="px-4 py-2 text-right font-medium">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in report.expense_lines" :key="e.id" class="border-t">
                                <td class="px-4 py-1.5">{{ e.category }}</td>
                                <td class="px-4 py-1.5">{{ e.description }}</td>
                                <td class="px-4 py-1.5">{{ paymentModeLabel(e.payment_mode) }}</td>
                                <td class="px-4 py-1.5 text-right tabular-nums">{{ formatMoney(e.amount) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t bg-muted/30 font-semibold">
                                <td colspan="3" class="px-4 py-2">Total expenses</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(report.expenses.total) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <section class="rounded-xl border bg-card shadow-sm">
                <header class="flex items-center justify-between border-b px-4 py-3">
                    <h2 class="text-sm font-semibold">By staff</h2>
                    <Link v-if="isOwner" href="/reports/staff" class="text-xs text-primary hover:underline print:hidden">Staff report →</Link>
                </header>
                <EmptyState v-if="report.by_staff.length === 0" title="No services in this period" class="m-4" />
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Staff</th>
                                <th class="px-4 py-2 text-right font-medium">Services</th>
                                <th class="px-4 py-2 text-right font-medium">Invoices</th>
                                <th class="px-4 py-2 text-right font-medium">Revenue</th>
                                <th class="px-4 py-2 text-right font-medium">Commission</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in report.by_staff" :key="s.staff_member_id ?? 'none'" class="border-t">
                                <td class="px-4 py-1.5">{{ s.name }}</td>
                                <td class="px-4 py-1.5 text-right tabular-nums">{{ s.services_count }}</td>
                                <td class="px-4 py-1.5 text-right tabular-nums">{{ s.invoices_count }}</td>
                                <td class="px-4 py-1.5 text-right tabular-nums">{{ formatMoney(s.revenue) }}</td>
                                <td class="px-4 py-1.5 text-right tabular-nums text-muted-foreground">
                                    {{ s.commission_percent > 0 ? formatMoney(s.commission) : '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-xl border bg-card p-4 shadow-sm">
                <dl class="grid grid-cols-2 gap-y-2 text-sm sm:max-w-md">
                    <dt>Earnings</dt>
                    <dd class="text-right tabular-nums">{{ formatMoney(report.earnings.total) }}</dd>
                    <dt>Expenses</dt>
                    <dd class="text-right tabular-nums">− {{ formatMoney(report.expenses.total) }}</dd>
                    <dt class="border-t pt-2 text-base font-semibold">Net</dt>
                    <dd
                        class="border-t pt-2 text-right text-base font-semibold tabular-nums"
                        :class="report.net < 0 ? 'text-red-700 dark:text-red-400' : ''"
                    >
                        {{ formatMoney(report.net) }}
                    </dd>
                    <dt class="text-muted-foreground">Cash in hand (cash earnings − cash expenses)</dt>
                    <dd class="text-right tabular-nums">{{ formatMoney(report.cash_in_hand) }}</dd>
                </dl>
            </section>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    aside,
    header,
    [data-sidebar],
    .print\:hidden {
        display: none !important;
    }
    body {
        background: #fff !important;
        color: #000 !important;
    }
}
</style>
