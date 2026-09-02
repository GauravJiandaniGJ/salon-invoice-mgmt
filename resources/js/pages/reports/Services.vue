<script setup lang="ts">
import DateInput from '@/components/DateInput.vue';
import EmptyState from '@/components/EmptyState.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { addMonths, currentMonth, formatDate, monthRange, todayIso } from '@/lib/dates';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type ServicesReport } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Printer } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import ReportTabs from './ReportTabs.vue';

const props = defineProps<{ report: ServicesReport }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: '/reports/daily' },
    { title: 'Services', href: '/reports/services' },
];

const from = ref(props.report.from);
const to = ref(props.report.to);
watch(
    () => props.report,
    (r) => {
        from.value = r.from;
        to.value = r.to;
    },
);

const apply = () => router.get('/reports/services', { from: from.value, to: to.value }, { preserveState: true, preserveScroll: true });

const preset = (which: 'this' | 'last' | 'today') => {
    if (which === 'today') {
        from.value = todayIso();
        to.value = todayIso();
    } else {
        const r = monthRange(which === 'this' ? currentMonth() : addMonths(currentMonth(), -1));
        from.value = r.from;
        to.value = r.to;
    }
    apply();
};

const csvHref = () => `/reports/services.csv?from=${props.report.from}&to=${props.report.to}`;
const printPage = () => window.print();
</script>

<template>
    <Head title="Services report" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 print:p-0">
            <ReportTabs active="services" />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Services report</h1>
                    <p class="text-sm text-muted-foreground">{{ formatDate(report.from) }} – {{ formatDate(report.to) }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 print:hidden">
                    <Button variant="outline" size="sm" @click="printPage"><Printer /> Print</Button>
                    <Button as-child variant="ghost" size="sm"><a :href="csvHref()">CSV</a></Button>
                </div>
            </div>

            <form class="flex flex-wrap items-end gap-2 rounded-xl border bg-card shadow-sm p-3 print:hidden" @submit.prevent="apply">
                <div class="grid gap-1">
                    <label class="text-xs font-medium" for="svc-from">From</label>
                    <DateInput id="svc-from" v-model="from" :max="to" class="h-9" />
                </div>
                <div class="grid gap-1">
                    <label class="text-xs font-medium" for="svc-to">To</label>
                    <DateInput id="svc-to" v-model="to" :min="from" :max="todayIso()" class="h-9" />
                </div>
                <Button type="submit" size="sm" class="h-9">Apply</Button>
                <div class="ml-auto flex gap-1">
                    <Button type="button" variant="ghost" size="sm" @click="preset('today')">Today</Button>
                    <Button type="button" variant="ghost" size="sm" @click="preset('this')">This month</Button>
                    <Button type="button" variant="ghost" size="sm" @click="preset('last')">Last month</Button>
                </div>
            </form>

            <div class="grid gap-3 sm:grid-cols-2">
                <StatCard label="Services billed" :value="String(report.totals.count)" />
                <StatCard label="Revenue" :value="formatMoney(report.totals.revenue)" tone="positive" />
            </div>

            <section class="rounded-xl border bg-card shadow-sm">
                <EmptyState v-if="report.rows.length === 0" title="No services billed in this range" class="m-4" />
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">#</th>
                                <th class="px-4 py-2 font-medium">Service</th>
                                <th class="px-4 py-2 text-right font-medium">Times billed</th>
                                <th class="px-4 py-2 text-right font-medium">Qty</th>
                                <th class="px-4 py-2 text-right font-medium">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in report.rows" :key="`${row.service_id}-${row.description}`" class="border-t">
                                <td class="px-4 py-1.5 text-muted-foreground">{{ i + 1 }}</td>
                                <td class="px-4 py-1.5">
                                    {{ row.description }}
                                    <span
                                        v-if="row.service_id === null"
                                        class="ml-1 rounded bg-muted px-1.5 py-0.5 text-[10px] uppercase text-muted-foreground"
                                        >custom</span
                                    >
                                </td>
                                <td class="px-4 py-1.5 text-right tabular-nums">{{ row.count }}</td>
                                <td class="px-4 py-1.5 text-right tabular-nums">{{ row.quantity }}</td>
                                <td class="px-4 py-1.5 text-right tabular-nums">{{ formatMoney(row.revenue) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t bg-muted/30 font-semibold">
                                <td colspan="2" class="px-4 py-2">Total</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ report.totals.count }}</td>
                                <td></td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(report.totals.revenue) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
