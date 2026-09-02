<script setup lang="ts">
import DateInput from '@/components/DateInput.vue';
import EmptyState from '@/components/EmptyState.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { addDays, todayIso } from '@/lib/dates';
import { byModeRows, paymentModeLabel } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type DailyReport } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Download, FileText, Printer } from 'lucide-vue-next';
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

const goTo = (date: string) => router.get('/reports/daily', { date }, { preserveState: true, preserveScroll: true });

const earningsRows = computed(() => byModeRows(props.report.earnings.by_mode));
const expenseRows = computed(() => byModeRows(props.report.expenses.by_mode));
const isToday = computed(() => props.report.date === todayIso());
const printPage = () => window.print();
</script>

<template>
    <Head :title="`Daily statement · ${report.date_label}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 print:p-0">
            <ReportTabs active="daily" />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Daily statement</h1>
                    <p class="text-sm text-muted-foreground">{{ report.date_label }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-2 print:hidden">
                    <template v-if="can_pick_date">
                        <Button variant="outline" size="icon" aria-label="Previous day" @click="goTo(addDays(report.date, -1))"
                            ><ChevronLeft
                        /></Button>
                        <DateInput :model-value="report.date" :max="todayIso()" class="h-9" @update:model-value="goTo" />
                        <Button variant="outline" size="icon" aria-label="Next day" :disabled="isToday" @click="goTo(addDays(report.date, 1))"
                            ><ChevronRight
                        /></Button>
                        <Button v-if="!isToday" variant="ghost" size="sm" @click="goTo(todayIso())">Today</Button>
                    </template>
                    <Button variant="outline" size="sm" @click="printPage"><Printer /> Print</Button>
                    <Button as-child variant="outline" size="sm">
                        <a :href="`/reports/daily/pdf?date=${report.date}`" target="_blank" rel="noopener"><Download /> PDF</a>
                    </Button>
                    <Button as-child variant="ghost" size="sm">
                        <a :href="`/reports/daily.csv?date=${report.date}`">CSV</a>
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
                <EmptyState v-if="report.expense_lines.length === 0" title="No expenses on this day" class="m-4" />
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
