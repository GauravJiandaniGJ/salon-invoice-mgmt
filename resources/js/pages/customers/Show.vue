<script setup lang="ts">
import { formatDate, paymentLabel } from '@/components/billing/format';
import PaginationLinks from '@/components/billing/PaginationLinks.vue';
import StatusBadge from '@/components/billing/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/money';
import type { BreadcrumbItem, CustomerDetail, Gender, InvoiceRow, Paginated } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, FilePlus2, Pencil } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    customer: CustomerDetail;
    invoices: Paginated<InvoiceRow>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Customers', href: '/customers' },
    { title: props.customer.name, href: `/customers/${props.customer.id}` },
];

const editing = ref(false);
const form = useForm<{ name: string; phone: string; gender: Gender | null; notes: string }>({
    name: props.customer.name,
    phone: props.customer.phone_display,
    gender: props.customer.gender,
    notes: props.customer.notes ?? '',
});

const save = () => {
    form.patch(`/customers/${props.customer.id}`, {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
    });
};

const cancel = () => {
    form.reset();
    form.clearErrors();
    editing.value = false;
};

const genderLabel: Record<string, string> = { female: 'Female', male: 'Male', other: 'Other' };
</script>

<template>
    <Head :title="customer.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">{{ customer.name }}</h1>
                    <p class="text-sm text-muted-foreground">{{ customer.phone_display }} · Customer since {{ formatDate(customer.created_at) }}</p>
                </div>
                <Button as-child size="lg">
                    <Link :href="`/bills/new?customer_id=${customer.id}`"><FilePlus2 /> New Bill for {{ customer.name.split(' ')[0] }}</Link>
                </Button>
            </div>

            <div class="grid gap-4 lg:grid-cols-[320px_1fr]">
                <div class="space-y-4">
                    <div class="grid grid-cols-3 gap-2 lg:grid-cols-1">
                        <div class="rounded-xl border bg-card p-3 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Lifetime total</p>
                            <p class="text-xl font-bold tabular-nums">{{ formatMoney(customer.total_spent) }}</p>
                        </div>
                        <div class="rounded-xl border bg-card p-3 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Visits</p>
                            <p class="text-xl font-bold tabular-nums">{{ customer.visits }}</p>
                        </div>
                        <div class="rounded-xl border bg-card p-3 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Last visit</p>
                            <p class="text-xl font-bold">{{ formatDate(customer.last_visit_at) }}</p>
                        </div>
                    </div>

                    <section class="rounded-xl border bg-card p-4 shadow-sm">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Profile</h2>
                            <Button v-if="!editing" variant="ghost" size="sm" @click="editing = true"><Pencil /> Edit</Button>
                        </div>

                        <form v-if="editing" class="grid gap-3" @submit.prevent="save">
                            <div class="grid gap-1.5">
                                <Label for="c-name">Name</Label>
                                <Input id="c-name" v-model="form.name" required />
                                <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="c-phone">Phone</Label>
                                <Input id="c-phone" v-model="form.phone" type="tel" inputmode="numeric" required />
                                <p v-if="form.errors.phone" class="text-xs text-destructive">{{ form.errors.phone }}</p>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="c-gender">Gender</Label>
                                <select id="c-gender" v-model="form.gender" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                                    <option :value="null">—</option>
                                    <option value="female">Female</option>
                                    <option value="male">Male</option>
                                    <option value="other">Other</option>
                                </select>
                                <p v-if="form.errors.gender" class="text-xs text-destructive">{{ form.errors.gender }}</p>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="c-notes">Notes</Label>
                                <textarea
                                    id="c-notes"
                                    v-model="form.notes"
                                    rows="3"
                                    placeholder="Allergies, preferences…"
                                    class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                                />
                                <p v-if="form.errors.notes" class="text-xs text-destructive">{{ form.errors.notes }}</p>
                            </div>
                            <div class="flex gap-2">
                                <Button type="submit" :disabled="form.processing"><Check /> Save</Button>
                                <Button type="button" variant="ghost" @click="cancel">Cancel</Button>
                            </div>
                        </form>

                        <dl v-else class="space-y-2 text-sm">
                            <div>
                                <dt class="text-xs text-muted-foreground">Phone</dt>
                                <dd>{{ customer.phone_display }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Gender</dt>
                                <dd>{{ customer.gender ? genderLabel[customer.gender] : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Notes</dt>
                                <dd class="whitespace-pre-wrap">{{ customer.notes || '—' }}</dd>
                            </div>
                        </dl>
                    </section>
                </div>

                <section class="rounded-xl border bg-card shadow-sm">
                    <h2 class="border-b px-4 py-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Visit history</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Invoice</th>
                                    <th class="px-3 py-2 font-medium">Date</th>
                                    <th class="px-3 py-2 font-medium">Items</th>
                                    <th class="px-3 py-2 text-right font-medium">Total</th>
                                    <th class="px-3 py-2 font-medium">Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="invoices.data.length === 0">
                                    <td colspan="5" class="px-3 py-10 text-center text-muted-foreground">No bills yet.</td>
                                </tr>
                                <tr
                                    v-for="inv in invoices.data"
                                    :key="inv.id"
                                    class="border-t hover:bg-accent/40"
                                    :class="{ 'text-muted-foreground': inv.status === 'void' }"
                                >
                                    <td class="px-3 py-2 font-medium">
                                        <Link :href="`/invoices/${inv.id}`" class="hover:underline">{{ inv.invoice_number }}</Link>
                                        <StatusBadge
                                            v-if="inv.status === 'void' || inv.payment_status === 'unpaid'"
                                            :status="inv.status"
                                            :payment-status="inv.payment_status"
                                            class="ml-1"
                                        />
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2">{{ formatDate(inv.invoice_date) }}</td>
                                    <td class="max-w-[280px] truncate px-3 py-2" :title="inv.items_summary">{{ inv.items_summary }}</td>
                                    <td class="px-3 py-2 text-right font-semibold tabular-nums" :class="{ 'line-through': inv.status === 'void' }">
                                        {{ formatMoney(inv.total) }}
                                    </td>
                                    <td class="px-3 py-2">{{ paymentLabel(inv.payment_mode) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t p-3">
                        <PaginationLinks :paginator="invoices" />
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
