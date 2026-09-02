<script setup lang="ts">
import { getJson } from '@/components/billing/http';
import { formatDate } from '@/components/billing/format';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney } from '@/lib/money';
import { normalisePhone } from '@/lib/phone';
import type { CustomerLookup, CustomerLookupResponse, Gender, StaffMemberOption } from '@/types';
import { Link } from '@inertiajs/vue3';
import { CheckCircle2, LoaderCircle, UserPlus } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';

defineProps<{
    staffMembers: StaffMemberOption[];
    canEditDate: boolean;
    today: string;
    errors: Record<string, string>;
}>();

const phone = defineModel<string>('phone', { required: true });
const name = defineModel<string>('name', { required: true });
const gender = defineModel<Gender | null>('gender', { required: true });
const staffMemberId = defineModel<number | null>('staffMemberId', { required: true });
const invoiceDate = defineModel<string | null>('invoiceDate', { required: true });

const emit = defineEmits<{ (e: 'customer-found', customer: CustomerLookup): void; (e: 'customer-new'): void }>();

type LookupState = 'idle' | 'loading' | 'found' | 'new' | 'invalid';
const state = ref<LookupState>('idle');
const found = ref<CustomerLookup | null>(null);
const lookupError = ref<string | null>(null);
const nameWasAutofilled = ref(false);

let timer: ReturnType<typeof setTimeout> | null = null;
let controller: AbortController | null = null;
let lastLookedUp = '';

const lookup = async (raw: string) => {
    const normalised = normalisePhone(raw);
    if (!normalised) {
        state.value = 'invalid';
        found.value = null;
        lookupError.value = 'Enter a valid 10-digit Indian mobile number.';
        return;
    }
    if (normalised === lastLookedUp && state.value !== 'idle' && state.value !== 'loading') return;
    lastLookedUp = normalised;

    controller?.abort();
    controller = new AbortController();
    state.value = 'loading';
    lookupError.value = null;

    try {
        const res = await getJson<CustomerLookupResponse>(`/customers/lookup?phone=${encodeURIComponent(normalised)}`, controller.signal);
        if (res.error) {
            state.value = 'invalid';
            found.value = null;
            lookupError.value = res.error;
            return;
        }
        if (res.found && res.customer) {
            found.value = res.customer;
            state.value = 'found';
            if (!name.value.trim() || nameWasAutofilled.value) {
                name.value = res.customer.name;
                nameWasAutofilled.value = true;
            }
            if (!gender.value && res.customer.gender) gender.value = res.customer.gender;
            emit('customer-found', res.customer);
        } else {
            found.value = null;
            state.value = 'new';
            if (nameWasAutofilled.value) {
                name.value = '';
                nameWasAutofilled.value = false;
            }
            emit('customer-new');
        }
    } catch (e) {
        if ((e as Error).name === 'AbortError') return;
        state.value = 'idle';
        lookupError.value = 'Could not look up this number. Check your connection.';
    }
};

watch(
    phone,
    (value) => {
        if (timer) clearTimeout(timer);
        const digits = value.replace(/\D+/g, '');
        if (digits.length < 10) {
            state.value = 'idle';
            found.value = null;
            lookupError.value = null;
            lastLookedUp = '';
            return;
        }
        timer = setTimeout(() => lookup(value), 300);
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (timer) clearTimeout(timer);
    controller?.abort();
});

const onNameInput = () => (nameWasAutofilled.value = false);
</script>

<template>
    <section class="rounded-lg border bg-card p-4">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Customer</h2>

        <div class="grid gap-3 sm:grid-cols-2">
            <div class="grid gap-1.5">
                <Label for="phone">Phone</Label>
                <div class="relative">
                    <Input
                        id="phone"
                        v-model="phone"
                        type="tel"
                        inputmode="numeric"
                        autocomplete="off"
                        autofocus
                        placeholder="98765 43210"
                        class="h-11 pr-9 text-base"
                        :class="{ 'border-destructive': errors['customer.phone'] || state === 'invalid' }"
                    />
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                        <LoaderCircle v-if="state === 'loading'" class="h-4 w-4 animate-spin text-muted-foreground" />
                        <CheckCircle2 v-else-if="state === 'found'" class="h-4 w-4 text-emerald-600" />
                        <UserPlus v-else-if="state === 'new'" class="h-4 w-4 text-blue-600" />
                    </span>
                </div>
                <p v-if="errors['customer.phone'] || lookupError" class="text-xs text-destructive">{{ errors['customer.phone'] || lookupError }}</p>
            </div>

            <div class="grid gap-1.5">
                <Label for="customer-name">Name <span v-if="state === 'new'" class="text-destructive">*</span></Label>
                <Input
                    id="customer-name"
                    v-model="name"
                    autocomplete="off"
                    :placeholder="state === 'new' ? 'New customer — enter name' : 'Customer name'"
                    class="h-11 text-base"
                    :class="{ 'border-destructive': errors['customer.name'] }"
                    @input="onNameInput"
                />
                <p v-if="errors['customer.name']" class="text-xs text-destructive">{{ errors['customer.name'] }}</p>
            </div>
        </div>

        <p v-if="state === 'found' && found" class="mt-2 flex flex-wrap items-center gap-x-2 text-sm text-muted-foreground">
            <span class="font-medium text-foreground">{{ found.name }}</span>
            <span v-if="found.last_invoice">· Last visit {{ formatDate(found.last_invoice.invoice_date) }}, {{ formatMoney(found.last_invoice.total) }}</span>
            <span v-else-if="found.last_visit_at">· Last visit {{ formatDate(found.last_visit_at) }}</span>
            <span>· {{ found.visits }} visit{{ found.visits === 1 ? '' : 's' }}</span>
            <Link :href="`/customers/${found.id}`" class="underline underline-offset-2 hover:text-foreground">History</Link>
        </p>
        <p v-else-if="state === 'new'" class="mt-2 text-sm text-blue-700 dark:text-blue-300">New customer — they'll be saved with this bill.</p>

        <div class="mt-3 grid gap-3" :class="canEditDate ? 'sm:grid-cols-3' : 'sm:grid-cols-2'">
            <div class="grid gap-1.5">
                <Label for="gender">Gender <span class="text-muted-foreground">(optional)</span></Label>
                <select id="gender" v-model="gender" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                    <option :value="null">—</option>
                    <option value="female">Female</option>
                    <option value="male">Male</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <Label for="staff">Served by <span class="text-muted-foreground">(optional)</span></Label>
                <select id="staff" v-model="staffMemberId" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                    <option :value="null">—</option>
                    <option v-for="s in staffMembers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <p v-if="errors.staff_member_id" class="text-xs text-destructive">{{ errors.staff_member_id }}</p>
            </div>
            <div v-if="canEditDate" class="grid gap-1.5">
                <Label for="invoice-date">Bill date</Label>
                <Input id="invoice-date" type="date" :model-value="invoiceDate ?? today" :max="today" class="h-10" @update:model-value="(v) => (invoiceDate = String(v) === today ? null : String(v))" />
                <p v-if="errors.invoice_date" class="text-xs text-destructive">{{ errors.invoice_date }}</p>
            </div>
        </div>
    </section>
</template>
