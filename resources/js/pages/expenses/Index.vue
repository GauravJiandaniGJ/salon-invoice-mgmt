<script setup lang="ts">
import DateInput from '@/components/DateInput.vue';
import EmptyState from '@/components/EmptyState.vue';
import MoneyInput from '@/components/MoneyInput.vue';
import PaymentModeChips from '@/components/PaymentModeChips.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { addMonths, formatDate, formatMonth, todayIso } from '@/lib/dates';
import { byModeRows, paymentModeLabel } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type ByMode, type ExpenseRow, type PaymentMode } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Pencil, Trash2, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    month: string;
    expenses: ExpenseRow[];
    totals: { total: number; by_mode: ByMode };
    categories: string[];
    payment_modes: PaymentMode[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Expenses', href: '/expenses' }];

const goToMonth = (month: string) => router.get('/expenses', { month }, { preserveState: true, preserveScroll: true });

// ----- quick add -----
const form = useForm({
    expense_date: todayIso(),
    category: '',
    description: '',
    amount: null as number | null,
    payment_mode: 'cash' as PaymentMode,
});

const submit = () => {
    form.post('/expenses', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('category', 'description', 'amount');
            form.clearErrors();
        },
    });
};

// ----- edit in place -----
const editingId = ref<number | null>(null);
const editForm = useForm({
    expense_date: '',
    category: '',
    description: '',
    amount: null as number | null,
    payment_mode: 'cash' as PaymentMode,
});

const startEdit = (e: ExpenseRow) => {
    editingId.value = e.id;
    editForm.clearErrors();
    editForm.expense_date = e.expense_date;
    editForm.category = e.category;
    editForm.description = e.description;
    editForm.amount = e.amount;
    editForm.payment_mode = e.payment_mode;
};

const saveEdit = () => {
    if (editingId.value === null) return;
    editForm.patch(`/expenses/${editingId.value}`, {
        preserveScroll: true,
        onSuccess: () => (editingId.value = null),
    });
};

const remove = (e: ExpenseRow) => {
    if (!confirm(`Delete "${e.description}" (${formatMoney(e.amount)})?`)) return;
    router.delete(`/expenses/${e.id}`, { preserveScroll: true });
};

const modeRows = computed(() => byModeRows(props.totals.by_mode));
</script>

<template>
    <Head title="Expenses" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4">
            <div>
                <h1 class="text-2xl font-semibold">Expenses</h1>
                <p class="text-sm text-muted-foreground">Record what the salon spends so the daily statement shows the real net.</p>
            </div>

            <form class="rounded-xl border bg-card p-4 shadow-sm" @submit.prevent="submit">
                <h2 class="mb-3 text-sm font-semibold">Quick add</h2>
                <div class="grid gap-3 md:grid-cols-12">
                    <div class="grid gap-1 md:col-span-2">
                        <Label for="exp-date">Date</Label>
                        <DateInput id="exp-date" v-model="form.expense_date" :max="todayIso()" />
                        <p v-if="form.errors.expense_date" class="text-xs text-destructive">{{ form.errors.expense_date }}</p>
                    </div>
                    <div class="grid gap-1 md:col-span-2">
                        <Label for="exp-category">Category</Label>
                        <Input id="exp-category" v-model="form.category" list="expense-categories" placeholder="Products, Rent…" required />
                        <datalist id="expense-categories">
                            <option v-for="c in categories" :key="c" :value="c" />
                        </datalist>
                        <p v-if="form.errors.category" class="text-xs text-destructive">{{ form.errors.category }}</p>
                    </div>
                    <div class="grid gap-1 md:col-span-4">
                        <Label for="exp-desc">Description</Label>
                        <Input id="exp-desc" v-model="form.description" placeholder="e.g. Shampoo stock from supplier" required />
                        <p v-if="form.errors.description" class="text-xs text-destructive">{{ form.errors.description }}</p>
                    </div>
                    <div class="grid gap-1 md:col-span-2">
                        <Label for="exp-amount">Amount</Label>
                        <MoneyInput id="exp-amount" v-model="form.amount" />
                        <p v-if="form.errors.amount" class="text-xs text-destructive">{{ form.errors.amount }}</p>
                    </div>
                    <div class="grid gap-1 md:col-span-2">
                        <Label>Paid by</Label>
                        <PaymentModeChips v-model="form.payment_mode" :modes="payment_modes" size="sm" />
                        <p v-if="form.errors.payment_mode" class="text-xs text-destructive">{{ form.errors.payment_mode }}</p>
                    </div>
                </div>
                <div class="mt-3 flex justify-end">
                    <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Add expense' }}</Button>
                </div>
            </form>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-1">
                    <Button variant="outline" size="icon" aria-label="Previous month" @click="goToMonth(addMonths(month, -1))"
                        ><ChevronLeft
                    /></Button>
                    <DateInput type="month" :model-value="month" class="h-9" @update:model-value="goToMonth" />
                    <Button variant="outline" size="icon" aria-label="Next month" @click="goToMonth(addMonths(month, 1))"><ChevronRight /></Button>
                </div>
                <div class="text-sm">
                    <span class="text-muted-foreground">Total for {{ formatMonth(month) }}:</span>
                    <span class="ml-1 font-semibold tabular-nums">{{ formatMoney(totals.total) }}</span>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 text-xs">
                <span v-for="row in modeRows" :key="row.mode" class="rounded-full border bg-card px-2.5 py-1 tabular-nums">
                    {{ row.label }}: {{ formatMoney(row.amount) }}
                </span>
            </div>

            <EmptyState
                v-if="expenses.length === 0"
                title="No expenses this month"
                description="Use the quick-add form above to record one."
                :icon="Wallet"
            />

            <div v-else class="overflow-x-auto rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">Date</th>
                            <th class="px-3 py-2 font-medium">Category</th>
                            <th class="px-3 py-2 font-medium">Description</th>
                            <th class="px-3 py-2 text-right font-medium">Amount</th>
                            <th class="hidden px-3 py-2 font-medium sm:table-cell">Paid by</th>
                            <th class="hidden px-3 py-2 font-medium md:table-cell">Entered by</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="e in expenses" :key="e.id">
                            <tr v-if="editingId === e.id" class="border-t bg-muted/40">
                                <td colspan="7" class="px-3 py-3">
                                    <form class="grid gap-2 md:grid-cols-12" @submit.prevent="saveEdit">
                                        <DateInput v-model="editForm.expense_date" class="h-9 md:col-span-2" />
                                        <Input
                                            v-model="editForm.category"
                                            list="expense-categories"
                                            class="h-9 md:col-span-2"
                                            placeholder="Category"
                                        />
                                        <Input v-model="editForm.description" class="h-9 md:col-span-4" placeholder="Description" />
                                        <MoneyInput v-model="editForm.amount" class="h-9 md:col-span-2" />
                                        <select
                                            v-model="editForm.payment_mode"
                                            class="h-9 rounded-md border border-input bg-background px-2 text-sm md:col-span-2"
                                        >
                                            <option v-for="m in payment_modes" :key="m" :value="m">{{ paymentModeLabel(m) }}</option>
                                        </select>
                                        <div class="flex gap-2 md:col-span-12 md:justify-end">
                                            <Button type="submit" size="sm" :disabled="editForm.processing">Save</Button>
                                            <Button type="button" size="sm" variant="ghost" @click="editingId = null">Cancel</Button>
                                        </div>
                                        <p v-if="Object.keys(editForm.errors).length" class="text-xs text-destructive md:col-span-12">
                                            {{ Object.values(editForm.errors).join(' ') }}
                                        </p>
                                    </form>
                                </td>
                            </tr>
                            <tr v-else class="border-t">
                                <td class="whitespace-nowrap px-3 py-2">{{ formatDate(e.expense_date) }}</td>
                                <td class="px-3 py-2">{{ e.category }}</td>
                                <td class="px-3 py-2">{{ e.description }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(e.amount) }}</td>
                                <td class="hidden px-3 py-2 sm:table-cell">{{ paymentModeLabel(e.payment_mode) }}</td>
                                <td class="hidden px-3 py-2 text-muted-foreground md:table-cell">{{ e.user.name }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right">
                                    <template v-if="e.can_edit">
                                        <Button variant="ghost" size="icon" class="h-8 w-8" title="Edit" @click="startEdit(e)"><Pencil /></Button>
                                        <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive" title="Delete" @click="remove(e)"
                                            ><Trash2
                                        /></Button>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr class="border-t bg-muted/30 font-semibold">
                            <td colspan="3" class="px-3 py-2">Total</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.total) }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
