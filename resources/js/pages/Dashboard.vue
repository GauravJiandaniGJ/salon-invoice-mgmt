<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { currentMonth, formatDate, formatDateLong, formatMonth, todayIso } from '@/lib/dates';
import { byModeRows, paymentModeLabel } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type DashboardData, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Check, FilePlus2, FileText, Send, X } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<DashboardData>();

const page = usePage<SharedData>();
const isOwner = computed(() => page.props.auth.user?.role === 'owner');

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const todayModes = computed(() => byModeRows(props.today.by_mode));
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Today</h1>
                    <p class="text-sm text-muted-foreground">{{ formatDateLong(todayIso()) }}</p>
                </div>
                <Button as-child size="lg" class="h-12 px-6 text-base">
                    <Link href="/bills/new"><FilePlus2 class="!size-5" /> New Bill</Link>
                </Button>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Invoices" :value="String(today.invoices_count)" />
                <StatCard label="Earnings" :value="formatMoney(today.earnings)" tone="positive" />
                <StatCard label="Expenses" :value="formatMoney(today.expenses)" tone="negative" />
                <StatCard label="Net" :value="formatMoney(today.net)" :tone="today.net < 0 ? 'negative' : 'default'" />
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-xl border bg-card shadow-sm p-4">
                    <h2 class="text-sm font-semibold">Earnings by payment mode</h2>
                    <ul class="mt-2 divide-y text-sm">
                        <li v-for="row in todayModes" :key="row.mode" class="flex items-center justify-between py-1.5">
                            <span>{{ row.label }}</span>
                            <span class="tabular-nums">{{ formatMoney(row.amount) }}</span>
                        </li>
                    </ul>
                </div>

                <div v-if="isOwner && month" class="rounded-xl border bg-card shadow-sm p-4">
                    <h2 class="text-sm font-semibold">This month · {{ formatMonth(currentMonth()) }}</h2>
                    <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <dt class="text-muted-foreground">Invoices</dt>
                        <dd class="text-right tabular-nums">{{ month.invoices_count }}</dd>
                        <dt class="text-muted-foreground">Earnings</dt>
                        <dd class="text-right tabular-nums">{{ formatMoney(month.earnings) }}</dd>
                        <dt class="text-muted-foreground">Expenses</dt>
                        <dd class="text-right tabular-nums">{{ formatMoney(month.expenses) }}</dd>
                        <dt class="font-medium">Net</dt>
                        <dd class="text-right font-semibold tabular-nums" :class="month.net < 0 ? 'text-red-700 dark:text-red-400' : ''">
                            {{ formatMoney(month.net) }}
                        </dd>
                    </dl>
                    <Link href="/reports/monthly" class="mt-3 inline-block text-xs text-primary underline-offset-4 hover:underline"
                        >Monthly report →</Link
                    >
                </div>

                <div class="rounded-xl border bg-card shadow-sm p-4">
                    <h2 class="text-sm font-semibold">Quick links</h2>
                    <div class="mt-2 flex flex-col gap-1 text-sm">
                        <Link href="/reports/daily" class="text-primary underline-offset-4 hover:underline">Daily statement</Link>
                        <Link href="/expenses" class="text-primary underline-offset-4 hover:underline">Add an expense</Link>
                        <Link href="/customers" class="text-primary underline-offset-4 hover:underline">Customers</Link>
                    </div>
                </div>
            </div>

            <section class="rounded-xl border bg-card shadow-sm">
                <header class="flex items-center justify-between border-b px-4 py-3">
                    <h2 class="text-sm font-semibold">Recent invoices</h2>
                    <Link href="/invoices" class="text-xs text-primary underline-offset-4 hover:underline">View all</Link>
                </header>

                <EmptyState
                    v-if="recent_invoices.length === 0"
                    title="No invoices yet"
                    description="Create your first bill to see it here."
                    :icon="FileText"
                    class="m-4"
                >
                    <Button as-child><Link href="/bills/new">New Bill</Link></Button>
                </EmptyState>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Invoice</th>
                                <th class="px-4 py-2 font-medium">Customer</th>
                                <th class="hidden px-4 py-2 font-medium md:table-cell">Items</th>
                                <th class="px-4 py-2 text-right font-medium">Total</th>
                                <th class="hidden px-4 py-2 font-medium sm:table-cell">Payment</th>
                                <th class="px-4 py-2 text-center font-medium">Sent</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="inv in recent_invoices"
                                :key="inv.id"
                                class="border-t"
                                :class="{ 'line-through opacity-60': inv.status === 'void' }"
                            >
                                <td class="whitespace-nowrap px-4 py-2">
                                    <Link :href="`/invoices/${inv.id}`" class="font-medium hover:underline">{{ inv.invoice_number }}</Link>
                                    <div class="text-xs text-muted-foreground">{{ formatDate(inv.invoice_date) }}</div>
                                </td>
                                <td class="px-4 py-2">
                                    <div>{{ inv.customer.name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ inv.customer.phone_display }}</div>
                                </td>
                                <td class="hidden max-w-[260px] truncate px-4 py-2 text-muted-foreground md:table-cell">{{ inv.items_summary }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(inv.total) }}</td>
                                <td class="hidden px-4 py-2 sm:table-cell">{{ paymentModeLabel(inv.payment_mode) }}</td>
                                <td class="px-4 py-2 text-center">
                                    <Check v-if="inv.whatsapp_sent_at" class="inline h-4 w-4 text-green-600" aria-label="Sent" />
                                    <X v-else class="inline h-4 w-4 text-muted-foreground" aria-label="Not sent" />
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <Button v-if="!inv.whatsapp_sent_at && inv.status === 'issued'" as-child size="sm" variant="outline">
                                        <Link :href="`/invoices/${inv.id}`"><Send /> Send</Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
