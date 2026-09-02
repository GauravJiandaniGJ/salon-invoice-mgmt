<script setup lang="ts">
import { formatDate } from '@/components/billing/format';
import { getJson } from '@/components/billing/http';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney } from '@/lib/money';
import { normalisePhone } from '@/lib/phone';
import type { CustomerLookup, CustomerLookupResponse, Gender, StaffMemberOption } from '@/types';
import { Link } from '@inertiajs/vue3';
import { CheckCircle2, LoaderCircle, UserPlus } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

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

// ----- exact lookup (10+ digits in the phone field) -----
type LookupState = 'idle' | 'loading' | 'found' | 'new' | 'invalid';
const state = ref<LookupState>('idle');
const found = ref<CustomerLookup | null>(null);
const lookupError = ref<string | null>(null);
const nameWasAutofilled = ref(false);

let timer: ReturnType<typeof setTimeout> | null = null;
let controller: AbortController | null = null;
let lastLookedUp = '';

const applyFound = (customer: CustomerLookup) => {
    found.value = customer;
    state.value = 'found';
    lookupError.value = null;
    if (!name.value.trim() || nameWasAutofilled.value || name.value === customer.name) {
        name.value = customer.name;
        nameWasAutofilled.value = true;
    }
    if (!gender.value && customer.gender) gender.value = customer.gender;
    emit('customer-found', customer);
};

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
            applyFound(res.customer);
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

// ----- suggestions (typing a name or a phone fragment in either field) -----
type Field = 'phone' | 'name';
const suggestions = ref<CustomerLookup[]>([]);
const suggestOpen = ref(false);
const suggestLoading = ref(false);
const activeIndex = ref(-1);
const activeField = ref<Field>('phone');
const selected = ref<{ phone: string; name: string } | null>(null);

let suggestTimer: ReturnType<typeof setTimeout> | null = null;
let suggestController: AbortController | null = null;

const closeSuggestions = () => {
    suggestOpen.value = false;
    activeIndex.value = -1;
    suggestController?.abort();
    if (suggestTimer) clearTimeout(suggestTimer);
    suggestLoading.value = false;
};

const searchSuggestions = (q: string, field: Field) => {
    if (suggestTimer) clearTimeout(suggestTimer);
    if (q.trim().length < 2) {
        suggestions.value = [];
        closeSuggestions();
        return;
    }
    activeField.value = field;
    suggestTimer = setTimeout(async () => {
        suggestController?.abort();
        suggestController = new AbortController();
        suggestLoading.value = true;
        try {
            const res = await getJson<CustomerLookupResponse>(`/customers/lookup?q=${encodeURIComponent(q.trim())}`, suggestController.signal);
            suggestions.value = res.matches ?? [];
            suggestOpen.value = suggestions.value.length > 0;
            activeIndex.value = suggestions.value.length > 0 ? 0 : -1;
        } catch (e) {
            if ((e as Error).name !== 'AbortError') suggestions.value = [];
        } finally {
            suggestLoading.value = false;
        }
    }, 250);
};

const selectSuggestion = (customer: CustomerLookup) => {
    selected.value = { phone: customer.phone, name: customer.name };
    lastLookedUp = customer.phone;
    phone.value = customer.phone;
    applyFound(customer);
    closeSuggestions();
    suggestions.value = [];
};

const onSuggestKeydown = (e: KeyboardEvent) => {
    if (!suggestOpen.value) return;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex.value = (activeIndex.value + 1) % suggestions.value.length;
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex.value = (activeIndex.value - 1 + suggestions.value.length) % suggestions.value.length;
    } else if (e.key === 'Enter') {
        if (activeIndex.value >= 0) {
            e.preventDefault();
            selectSuggestion(suggestions.value[activeIndex.value]);
        }
    } else if (e.key === 'Escape') {
        e.preventDefault();
        closeSuggestions();
    }
};

const hasLetters = (value: string) => /[a-z]/i.test(value);

watch(
    phone,
    (value) => {
        if (timer) clearTimeout(timer);
        const digits = value.replace(/\D+/g, '');

        // A selected suggestion wrote this value — nothing more to do.
        if (selected.value && value === selected.value.phone) return;

        if (!hasLetters(value) && digits.length >= 10) {
            closeSuggestions();
            timer = setTimeout(() => lookup(value), 300);
            return;
        }

        // Anything else: reset the exact-lookup state and treat the text as a search.
        if (state.value !== 'idle') {
            state.value = 'idle';
            found.value = null;
            lookupError.value = null;
            lastLookedUp = '';
        }
        searchSuggestions(value, 'phone');
    },
    { immediate: true },
);

watch(name, (value) => {
    if (selected.value && value === selected.value.name) return;
    if (nameWasAutofilled.value && found.value && value === found.value.name) return;
    searchSuggestions(value, 'name');
});

onBeforeUnmount(() => {
    if (timer) clearTimeout(timer);
    if (suggestTimer) clearTimeout(suggestTimer);
    controller?.abort();
    suggestController?.abort();
});

const onNameInput = () => {
    nameWasAutofilled.value = false;
    selected.value = null;
};
const onPhoneInput = () => {
    selected.value = null;
};

const onFieldBlur = () => {
    // Delay so a mousedown on a suggestion can complete first.
    setTimeout(() => closeSuggestions(), 120);
};

const lastVisitLabel = (c: CustomerLookup) => {
    if (c.last_invoice) return `Last visit ${formatDate(c.last_invoice.invoice_date)} · ${formatMoney(c.last_invoice.total)}`;
    if (c.last_visit_at) return `Last visit ${formatDate(c.last_visit_at)}`;
    return 'No visits yet';
};

const dropdownVisible = computed(() => suggestOpen.value && suggestions.value.length > 0);
</script>

<template>
    <section class="rounded-xl border bg-card p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Customer</h2>

        <div class="relative">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="phone">Phone <span class="font-normal text-muted-foreground">or search by name</span></Label>
                    <div class="relative">
                        <Input
                            id="phone"
                            v-model="phone"
                            type="text"
                            inputmode="tel"
                            autocomplete="off"
                            autofocus
                            role="combobox"
                            :aria-expanded="dropdownVisible && activeField === 'phone'"
                            aria-controls="customer-suggestions"
                            placeholder="98765 43210 or customer name"
                            class="h-11 pr-9 text-base"
                            :class="{ 'border-destructive': errors['customer.phone'] || state === 'invalid' }"
                            @input="onPhoneInput"
                            @keydown="onSuggestKeydown"
                            @blur="onFieldBlur"
                        />
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                            <LoaderCircle
                                v-if="state === 'loading' || (suggestLoading && activeField === 'phone')"
                                class="h-4 w-4 animate-spin text-muted-foreground"
                            />
                            <CheckCircle2 v-else-if="state === 'found'" class="h-4 w-4 text-emerald-600" />
                            <UserPlus v-else-if="state === 'new'" class="h-4 w-4 text-blue-600" />
                        </span>
                    </div>
                    <p v-if="errors['customer.phone'] || lookupError" class="text-xs text-destructive">
                        {{ errors['customer.phone'] || lookupError }}
                    </p>
                </div>

                <div class="grid gap-1.5">
                    <Label for="customer-name">Name <span v-if="state === 'new'" class="text-destructive">*</span></Label>
                    <div class="relative">
                        <Input
                            id="customer-name"
                            v-model="name"
                            autocomplete="off"
                            role="combobox"
                            :aria-expanded="dropdownVisible && activeField === 'name'"
                            aria-controls="customer-suggestions"
                            :placeholder="state === 'new' ? 'New customer — enter name' : 'Customer name'"
                            class="h-11 pr-9 text-base"
                            :class="{ 'border-destructive': errors['customer.name'] }"
                            @input="onNameInput"
                            @keydown="onSuggestKeydown"
                            @blur="onFieldBlur"
                        />
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                            <LoaderCircle v-if="suggestLoading && activeField === 'name'" class="h-4 w-4 animate-spin text-muted-foreground" />
                        </span>
                    </div>
                    <p v-if="errors['customer.name']" class="text-xs text-destructive">{{ errors['customer.name'] }}</p>
                </div>
            </div>

            <!-- suggestions dropdown (shared by both fields) -->
            <ul
                v-if="dropdownVisible"
                id="customer-suggestions"
                role="listbox"
                class="absolute left-0 right-0 z-20 mt-1 max-h-72 overflow-y-auto rounded-lg border bg-popover p-1 text-popover-foreground shadow-lg sm:left-auto sm:w-[28rem]"
                :class="activeField === 'phone' ? 'sm:left-0' : 'sm:right-0'"
            >
                <li class="px-2 py-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Existing customers · ↑↓ then Enter</li>
                <li
                    v-for="(c, i) in suggestions"
                    :key="c.id"
                    role="option"
                    :aria-selected="i === activeIndex"
                    :class="[
                        'flex cursor-pointer items-center justify-between gap-3 rounded-md px-3 py-2',
                        i === activeIndex ? 'bg-accent' : 'hover:bg-accent/60',
                    ]"
                    @mousedown.prevent
                    @mouseenter="activeIndex = i"
                    @click="selectSuggestion(c)"
                >
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium">{{ c.name }}</span>
                        <span class="block text-xs text-muted-foreground">{{ lastVisitLabel(c) }}</span>
                    </span>
                    <span class="shrink-0 text-sm tabular-nums text-muted-foreground">{{ c.phone_display }}</span>
                </li>
            </ul>
        </div>

        <p v-if="state === 'found' && found" class="mt-2 flex flex-wrap items-center gap-x-2 text-sm text-muted-foreground">
            <span class="font-medium text-foreground">{{ found.name }}</span>
            <span v-if="found.last_invoice"
                >· Last visit {{ formatDate(found.last_invoice.invoice_date) }}, {{ formatMoney(found.last_invoice.total) }}</span
            >
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
                <Input
                    id="invoice-date"
                    type="date"
                    :model-value="invoiceDate ?? today"
                    :max="today"
                    class="h-10"
                    @update:model-value="(v) => (invoiceDate = String(v) === today ? null : String(v))"
                />
                <p v-if="errors.invoice_date" class="text-xs text-destructive">{{ errors.invoice_date }}</p>
            </div>
        </div>
    </section>
</template>
