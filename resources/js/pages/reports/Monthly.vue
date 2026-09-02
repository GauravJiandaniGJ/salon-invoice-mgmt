<script setup lang="ts">
import DateInput from '@/components/DateInput.vue';
import EmptyState from '@/components/EmptyState.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { addMonths, currentMonth, formatDate } from '@/lib/dates';
import { byModeRows } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type MonthlyReport } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Printer } from 'lucide-vue-next';
import { computed } from 'vue';
import ReportTabs from './ReportTabs.vue';

const props = defineProps<{ report: MonthlyReport }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: '/reports/daily' },
    { title: 'Monthly', href: '/reports/monthly' },
];

const goTo = (month: string) => router.get('/reports/monthly', { month }, { preserveState: true, preserveScroll: true });

const earningsRows = computed(() => byModeRows(props.report.earnings_by_mode));
const expenseRows = computed(() => byModeRows(props.report.expenses_by_mode));
const isCurrent = computed(() => props.report.month === currentMonth());
const printPage = () => window.print();
</script>

<template>
    <Head :title="`Monthly report · ${report.month_label}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 print:p-0">
            <ReportTabs active="monthly" />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Monthly report</h1>
                    <p class="text-sm text-muted-foreground">{{ report.month_label }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 print:hidden">
                    <Button variant="outline" size="icon" aria-label="Previous month" @click="goTo(addMonths(report.month, -1))"
                        ><ChevronLeft
                    /></Button>
                    <DateInput type="month" :model-value="report.month" :max="currentMonth()" class="h-9" @update:model-value="goTo" />
                    <Button variant="outline" size="icon" aria-label="Next month" :disabled="isCurrent" @click="goTo(addMonths(report.month, 1))"
                        ><ChevronRight
                    /></Button>
                    <Button variant="outline" size="sm" @click="printPage"><Printer /> Print</Button>
                    <Button as-child variant="ghost" size="sm"><a :href="`/reports/monthly.csv?month=${report.month}`">CSV</a></Button>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Invoices" :value="String(report.totals.invoices_count)" />
                <StatCard label="Earnings" :value="formatMoney(report.totals.earnings)" tone="positive" />
                <StatCard label="Expenses" :value="formatMoney(report.totals.expenses)" tone="negative" />
                <StatCard label="Net" :value="formatMoney(report.totals.net)" :tone="report.totals.net < 0 ? 'negative' : 'default'" />
            </div>

            <section class="rounded-xl border bg-card shadow-sm">
                <header class="border-b px-4 py-3"><h2 class="text-sm font-semibold">Day by day</h2></header>
                <EmptyState v-if="report.days.length === 0" title="Nothing recorded this month" class="m-4" />
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Date</th>
                                <th class="px-4 py-2 text-right font-medium">Invoices</th>
                                <th class="px-4 py-2 text-right font-medium">Earnings</th>
                                <th class="px-4 py-2 text-right font-medium">Expenses</th>
                                <th class="px-4 py-2 text-right font-medium">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="d in report.days"
                                :key="d.date"
                                class="border-t"
                                :class="{ 'text-muted-foreground': d.invoices_count === 0 && d.expenses === 0 }"
                            >
                                <td class="whitespace-nowrap px-4 py-1.5">
                                    <Link :href="`/reports/daily?date=${d.date}`" class="hover:underline">{{ formatDate(d.date) }}</Link>
                                </td>
                                <td class="px-4 py-1.5 text-right tabular-nums">{{ d.invoices_count }}</td>
                                <td class="px-4 py-1.5 text-right tabular-nums">{{ formatMoney(d.earnings) }}</td>
                                <td class="px-4 py-1.5 text-right tabular-nums">{{ formatMoney(d.expenses) }}</td>
                                <td class="px-4 py-1.5 text-right tabular-nums" :class="d.net < 0 ? 'text-red-700 dark:text-red-400' : ''">
                                    {{ formatMoney(d.net) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t bg-muted/30 font-semibold">
                                <td class="px-4 py-2">Total</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ report.totals.invoices_count }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(report.totals.earnings) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(report.totals.expenses) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(report.totals.net) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <div class="grid gap-4 md:grid-cols-2">
                <section class="rounded-xl border bg-card shadow-sm p-4">
                    <h2 class="text-sm font-semibold">Earnings by payment mode</h2>
                    <table class="mt-2 w-full text-sm">
                        <tbody>
                            <tr v-for="row in earningsRows" :key="row.mode" class="border-t">
                                <td class="py-1.5">{{ row.label }}</td>
                                <td class="py-1.5 text-right tabular-nums">{{ formatMoney(row.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
                <section class="rounded-xl border bg-card shadow-sm p-4">
                    <h2 class="text-sm font-semibold">Expenses by payment mode</h2>
                    <table class="mt-2 w-full text-sm">
                        <tbody>
                            <tr v-for="row in expenseRows" :key="row.mode" class="border-t">
                                <td class="py-1.5">{{ row.label }}</td>
                                <td class="py-1.5 text-right tabular-nums">{{ formatMoney(row.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <section class="rounded-xl border bg-card shadow-sm">
                    <header class="border-b px-4 py-3"><h2 class="text-sm font-semibold">Top 10 services by revenue</h2></header>
                    <EmptyState v-if="report.top_services.length === 0" title="No services billed" class="m-4" />
                    <table v-else class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Service</th>
                                <th class="px-4 py-2 text-right font-medium">Count</th>
                                <th class="px-4 py-2 text-right font-medium">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in report.top_services" :key="s.description" class="border-t">
                                <td class="px-4 py-1.5">{{ s.description }}</td>
                                <td class="px-4 py-1.5 text-right tabular-nums">{{ s.count }}</td>
                                <td class="px-4 py-1.5 text-right tabular-nums">{{ formatMoney(s.revenue) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="rounded-xl border bg-card shadow-sm">
                    <header class="border-b px-4 py-3"><h2 class="text-sm font-semibold">Earnings per staff member</h2></header>
                    <EmptyState
                        v-if="report.by_staff.length === 0"
                        title="No staff assigned on invoices"
                        description="Pick a staff member on the New Bill screen to track this."
                        class="m-4"
                    />
                    <table v-else class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Staff</th>
                                <th class="px-4 py-2 text-right font-medium">Invoices</th>
                                <th class="px-4 py-2 text-right font-medium">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in report.by_staff" :key="s.staff_member" class="border-t">
                                <td class="px-4 py-1.5">{{ s.staff_member }}</td>
                                <td class="px-4 py-1.5 text-right tabular-nums">{{ s.invoices_count }}</td>
                                <td class="px-4 py-1.5 text-right tabular-nums">{{ formatMoney(s.revenue) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
