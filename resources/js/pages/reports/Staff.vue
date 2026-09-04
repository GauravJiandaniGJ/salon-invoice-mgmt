<script setup lang="ts">
import DateRangePicker from '@/components/DateRangePicker.vue';
import EmptyState from '@/components/EmptyState.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { paymentModeLabel } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import type { BreadcrumbItem, StaffMemberOption, StaffReport } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, Printer, X } from 'lucide-vue-next';
import { computed } from 'vue';
import ReportTabs from './ReportTabs.vue';

const props = defineProps<{
    report: StaffReport;
    staff_members: StaffMemberOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: '/reports/daily' },
    { title: 'Staff', href: '/reports/staff' },
];

const query = computed(() => `from=${props.report.from}&to=${props.report.to}`);
const rangeLabel = computed(() => (props.report.from === props.report.to ? props.report.from : `${props.report.from} – ${props.report.to}`));

const visit = (params: Record<string, string | number | null>) =>
    router.get('/reports/staff', { from: props.report.from, to: props.report.to, ...params }, { preserveState: true, preserveScroll: true });

const changeRange = (range: { from: string; to: string }) =>
    router.get(
        '/reports/staff',
        { ...range, staff_member_id: props.report.selected?.staff_member_id ?? undefined },
        { preserveState: true, preserveScroll: true },
    );

const select = (id: number | null) => visit({ staff_member_id: id });
const printPage = () => window.print();
</script>

<template>
    <Head title="Staff report" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 print:p-0">
            <ReportTabs active="staff" />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Staff report</h1>
                    <p class="text-sm text-muted-foreground">{{ rangeLabel }} · service revenue per barber, before bill-level discounts</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 print:hidden">
                    <Button variant="outline" size="sm" @click="printPage"><Printer /> Print</Button>
                    <Button as-child variant="outline" size="sm"
                        ><a :href="`/reports/staff/pdf?${query}`"><Download /> PDF</a></Button
                    >
                    <Button as-child variant="ghost" size="sm"><a :href="`/reports/staff.csv?${query}`">CSV</a></Button>
                </div>
            </div>

            <div class="print:hidden">
                <DateRangePicker :from="report.from" :to="report.to" @change="changeRange" />
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Revenue" :value="formatMoney(report.totals.revenue)" tone="positive" />
                <StatCard label="Services" :value="String(report.totals.services_count)" />
                <StatCard label="Invoices" :value="String(report.totals.invoices_count)" />
                <StatCard label="Commission" :value="formatMoney(report.totals.commission)" hint="Set % per staff member in Settings" />
            </div>

            <section class="rounded-xl border bg-card shadow-sm">
                <header class="border-b px-4 py-3"><h2 class="text-sm font-semibold">By staff member</h2></header>
                <EmptyState
                    v-if="report.rows.length === 0"
                    title="No services in this period"
                    description="Pick 'Served by' on the New Bill screen, or set a barber per line, to track this."
                    class="m-4"
                />
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Staff</th>
                                <th class="px-4 py-2 text-right font-medium">Services</th>
                                <th class="px-4 py-2 text-right font-medium">Invoices</th>
                                <th class="px-4 py-2 text-right font-medium">Revenue</th>
                                <th class="px-4 py-2 text-right font-medium">Avg ticket</th>
                                <th class="px-4 py-2 text-right font-medium">Commission %</th>
                                <th class="px-4 py-2 text-right font-medium">Commission</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="r in report.rows"
                                :key="r.staff_member_id ?? 'none'"
                                class="border-t"
                                :class="[
                                    r.staff_member_id ? 'cursor-pointer hover:bg-muted/40' : '',
                                    report.selected && report.selected.staff_member_id === r.staff_member_id ? 'bg-primary/5' : '',
                                ]"
                                @click="r.staff_member_id ? select(r.staff_member_id) : null"
                            >
                                <td class="px-4 py-2 font-medium">{{ r.name }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ r.services_count }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ r.invoices_count }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(r.revenue) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(r.average_ticket) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums text-muted-foreground">
                                    {{ r.commission_percent > 0 ? `${r.commission_percent}%` : '—' }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ r.commission_percent > 0 ? formatMoney(r.commission) : '—' }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t bg-muted/30 font-semibold">
                                <td class="px-4 py-2">Total</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ report.totals.services_count }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ report.totals.invoices_count }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(report.totals.revenue) }}</td>
                                <td></td>
                                <td></td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(report.totals.commission) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p v-if="report.rows.length" class="px-4 py-2 text-xs text-muted-foreground print:hidden">
                    Click a name to see that person's invoices.
                </p>
            </section>

            <section v-if="report.selected" class="rounded-xl border bg-card shadow-sm">
                <header class="flex items-center justify-between border-b px-4 py-3">
                    <h2 class="text-sm font-semibold">
                        Invoices with services by {{ report.selected.name }} ({{ report.selected.invoices.length }})
                    </h2>
                    <Button variant="ghost" size="sm" class="print:hidden" @click="select(null)"><X /> Clear</Button>
                </header>
                <EmptyState v-if="report.selected.invoices.length === 0" title="No invoices in this period" class="m-4" />
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Invoice</th>
                                <th class="px-4 py-2 font-medium">Customer</th>
                                <th class="px-4 py-2 font-medium">Served by (bill)</th>
                                <th class="px-4 py-2 font-medium">Payment</th>
                                <th class="px-4 py-2 text-right font-medium">Bill total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="inv in report.selected.invoices" :key="inv.id" class="border-t">
                                <td class="px-4 py-1.5">
                                    <Link :href="`/invoices/${inv.id}`" class="font-medium hover:underline">{{ inv.invoice_number }}</Link>
                                </td>
                                <td class="px-4 py-1.5">{{ inv.customer_name }}</td>
                                <td class="px-4 py-1.5 text-muted-foreground">{{ inv.staff_member ?? '—' }}</td>
                                <td class="px-4 py-1.5">{{ paymentModeLabel(inv.payment_mode) }}</td>
                                <td class="px-4 py-1.5 text-right tabular-nums">{{ formatMoney(inv.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
