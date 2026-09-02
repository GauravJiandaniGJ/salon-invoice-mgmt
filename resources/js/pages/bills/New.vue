<script setup lang="ts">
import CustomerBlock from '@/components/billing/CustomerBlock.vue';
import ServicePicker from '@/components/billing/ServicePicker.vue';
import { paymentLabel } from '@/components/billing/format';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { calculateTotals } from '@/lib/invoiceTotals';
import { formatMoney } from '@/lib/money';
import type {
    Audience,
    BillPayload,
    BillPrefill,
    BreadcrumbItem,
    CatalogCategory,
    CatalogService,
    CustomerLookup,
    DiscountType,
    EditingInvoice,
    Gender,
    PaymentMode,
    PaymentStatus,
    StaffMemberOption,
} from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { LoaderCircle, Minus, Plus, Save, Trash2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';

const props = defineProps<{
    catalog: CatalogCategory[];
    staff_members: StaffMemberOption[];
    payment_modes: PaymentMode[];
    tax_rate: number;
    today: string;
    can_edit_date: boolean;
    prefill: BillPrefill | null;
    editing: EditingInvoice | null;
}>();

const isEditing = computed(() => props.editing !== null);
const pageTitle = computed(() => (props.editing ? `Edit ${props.editing.invoice_number}` : 'New Bill'));
const breadcrumbs = computed<BreadcrumbItem[]>(() =>
    props.editing
        ? [
              { title: 'Invoices', href: '/invoices' },
              { title: props.editing.invoice_number, href: `/invoices/${props.editing.id}` },
              { title: 'Edit', href: `/invoices/${props.editing.id}/edit` },
          ]
        : [{ title: 'New Bill', href: '/bills/new' }],
);

// ----- state -----
interface Line {
    uid: number;
    service_id: number | null;
    description: string;
    unit_price: number | string;
    quantity: number | string;
}

let uidSeq = 1;
const newLine = (partial: Omit<Line, 'uid'>): Line => ({ uid: uidSeq++, ...partial });

const customer = reactive<{ phone: string; name: string; gender: Gender | null }>({
    phone: props.prefill?.customer?.phone ?? '',
    name: props.prefill?.customer?.name ?? '',
    gender: props.prefill?.customer?.gender ?? null,
});
const staffMemberId = ref<number | null>(props.prefill?.staff_member_id ?? null);
const invoiceDate = ref<string | null>(props.editing ? (props.prefill?.invoice_date ?? null) : null);
const lines = ref<Line[]>((props.prefill?.items ?? []).map((i) => newLine({ ...i })));
const discountType = ref<DiscountType | null>(props.prefill?.discount_type ?? null);
const discountValue = ref<number | string>(props.prefill?.discount_value ?? 0);
const paymentMode = ref<PaymentMode>(props.prefill?.payment_mode ?? 'cash');
const paymentStatus = ref<PaymentStatus>(props.prefill?.payment_status ?? 'paid');
const notes = ref(props.prefill?.notes ?? '');
const showNotes = ref(Boolean(props.prefill?.notes));
const audience = ref<Audience>(customer.gender === 'female' ? 'women' : customer.gender === 'male' ? 'men' : 'all');

const errors = ref<Record<string, string>>({});
const saving = ref(false);
const picker = ref<InstanceType<typeof ServicePicker> | null>(null);

// ----- lines -----
const addService = (service: CatalogService, price: number) => {
    const existing = lines.value.find((l) => l.service_id === service.id && Number(l.unit_price) === price);
    if (existing) {
        existing.quantity = Number(existing.quantity) + 1;
        return;
    }
    lines.value.push(newLine({ service_id: service.id, description: service.display_name, unit_price: price, quantity: 1 }));
};

const addCustom = (description: string, price: number) => {
    lines.value.push(newLine({ service_id: null, description, unit_price: price, quantity: 1 }));
};

const removeLine = (uid: number) => (lines.value = lines.value.filter((l) => l.uid !== uid));
const bumpQty = (line: Line, delta: number) => {
    const next = Number(line.quantity) + delta;
    if (next <= 0) removeLine(line.uid);
    else line.quantity = next;
};

// ----- totals -----
const totals = computed(() => calculateTotals(lines.value, discountType.value, discountValue.value, props.tax_rate));

const toggleDiscount = (type: DiscountType) => {
    if (discountType.value === type) {
        discountType.value = null;
        discountValue.value = 0;
    } else {
        discountType.value = type;
    }
};

const onCustomerFound = (found: CustomerLookup) => {
    if (found.gender === 'female') audience.value = 'women';
    else if (found.gender === 'male') audience.value = 'men';
};

// ----- save -----
const canSave = computed(() => lines.value.length > 0 && customer.phone.replace(/\D+/g, '').length >= 10 && !saving.value);

const payload = (): BillPayload => ({
    customer: { phone: customer.phone, name: customer.name.trim(), gender: customer.gender },
    staff_member_id: staffMemberId.value,
    invoice_date: props.can_edit_date ? invoiceDate.value : null,
    items: lines.value.map((l) => ({
        service_id: l.service_id,
        description: l.description.trim(),
        unit_price: Number(l.unit_price) || 0,
        quantity: Number(l.quantity) || 0,
    })),
    discount_type: discountType.value,
    discount_value: discountType.value ? Number(discountValue.value) || 0 : 0,
    payment_mode: paymentMode.value,
    payment_status: paymentStatus.value,
    notes: notes.value.trim(),
});

const save = () => {
    if (!canSave.value) return;
    errors.value = {};
    const body = payload() as unknown as Record<string, unknown>;
    const visit = props.editing ? router.put.bind(router, `/invoices/${props.editing.id}`) : router.post.bind(router, '/invoices');
    visit(body, {
        onStart: () => (saving.value = true),
        onFinish: () => (saving.value = false),
        onError: (e) => {
            errors.value = e as Record<string, string>;
            const first = Object.keys(e)[0];
            if (first) document.querySelector<HTMLElement>(`[data-error-anchor="${first.split('.')[0]}"]`)?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        },
    });
};

const onKeydown = (e: KeyboardEvent) => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        save();
    }
};
onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

const itemsError = computed(() => errors.value.items ?? null);
const lineError = (index: number) =>
    errors.value[`items.${index}.unit_price`] || errors.value[`items.${index}.quantity`] || errors.value[`items.${index}.description`] || errors.value[`items.${index}.service_id`] || null;
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            v-if="isEditing && editing"
            class="mx-4 mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200"
        >
            <div>
                <p class="font-semibold">Editing {{ editing.invoice_number }}</p>
                <p v-if="editing.whatsapp_sent_at" class="text-xs">This bill was already sent on WhatsApp. Saving will require sending it again.</p>
                <p v-else class="text-xs">The invoice number stays the same; totals and the PDF are regenerated.</p>
            </div>
            <Button as-child variant="outline" size="sm">
                <Link :href="`/invoices/${editing.id}`">Cancel</Link>
            </Button>
        </div>

        <div class="flex flex-1 flex-col gap-4 p-4 lg:flex-row lg:overflow-hidden" :class="isEditing ? 'lg:h-[calc(100vh-8.5rem)]' : 'lg:h-[calc(100vh-4rem)]'">
            <!-- LEFT: customer + picker -->
            <div class="flex min-h-0 flex-1 flex-col gap-4" data-error-anchor="customer">
                <CustomerBlock
                    v-model:phone="customer.phone"
                    v-model:name="customer.name"
                    v-model:gender="customer.gender"
                    v-model:staff-member-id="staffMemberId"
                    v-model:invoice-date="invoiceDate"
                    :staff-members="staff_members"
                    :can-edit-date="can_edit_date"
                    :today="today"
                    :errors="errors"
                    @customer-found="onCustomerFound"
                />
                <ServicePicker ref="picker" v-model:audience="audience" :catalog="catalog" class="lg:min-h-0" @add="addService" @add-custom="addCustom" />
            </div>

            <!-- RIGHT: the bill — header / scrollable lines / fixed footer with totals + save -->
            <aside
                class="flex w-full flex-col rounded-xl border bg-card shadow-sm lg:sticky lg:top-4 lg:max-h-[calc(100vh-6rem)] lg:w-[420px] lg:shrink-0"
                data-error-anchor="items"
            >
                <div class="flex shrink-0 items-center justify-between border-b px-4 py-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Bill</h2>
                    <span v-if="lines.length" class="text-xs text-muted-foreground">{{ lines.length }} line{{ lines.length === 1 ? '' : 's' }}</span>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
                    <div v-if="lines.length === 0" class="m-4 rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                        No services yet. Click a service on the left to add it.
                    </div>
                    <p v-if="itemsError" class="px-4 pt-2 text-xs text-destructive">{{ itemsError }}</p>

                    <ul v-if="lines.length" class="divide-y">
                        <li v-for="(line, index) in lines" :key="line.uid" class="p-3">
                            <div class="flex items-start gap-2">
                                <input
                                    v-model="line.description"
                                    class="min-w-0 flex-1 rounded-md border border-transparent bg-transparent px-1 py-0.5 text-sm font-medium hover:border-input focus:border-input focus:outline-none"
                                    aria-label="Description"
                                />
                                <button type="button" class="rounded p-1 text-muted-foreground hover:bg-accent hover:text-destructive" aria-label="Remove line" @click="removeLine(line.uid)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                            <div class="mt-1.5 flex items-center gap-2">
                                <span class="text-xs text-muted-foreground">₹</span>
                                <Input v-model="line.unit_price" type="number" min="0" step="any" inputmode="decimal" class="h-9 w-24 text-right" aria-label="Unit price" />
                                <span class="text-xs text-muted-foreground">×</span>
                                <div class="flex items-center rounded-md border">
                                    <button type="button" class="h-9 w-8 hover:bg-accent" aria-label="Decrease quantity" @click="bumpQty(line, -1)"><Minus class="mx-auto h-3.5 w-3.5" /></button>
                                    <input v-model="line.quantity" type="number" min="0.01" step="any" inputmode="decimal" class="h-9 w-12 border-x bg-transparent text-center text-sm focus:outline-none" aria-label="Quantity" />
                                    <button type="button" class="h-9 w-8 hover:bg-accent" aria-label="Increase quantity" @click="bumpQty(line, 1)"><Plus class="mx-auto h-3.5 w-3.5" /></button>
                                </div>
                                <span class="ml-auto text-sm font-semibold tabular-nums">{{ formatMoney(totals.line_totals[index]) }}</span>
                            </div>
                            <p v-if="lineError(index)" class="mt-1 text-xs text-destructive">{{ lineError(index) }}</p>
                        </li>
                    </ul>
                </div>

                <div class="shrink-0 border-t bg-muted/30 p-4">
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Subtotal</dt>
                            <dd class="tabular-nums">{{ formatMoney(totals.subtotal) }}</dd>
                        </div>

                        <div class="flex items-center justify-between gap-2" data-error-anchor="discount_value">
                            <dt class="flex items-center gap-1.5 text-muted-foreground">
                                Discount
                                <span class="flex rounded-md border bg-background p-0.5">
                                    <button type="button" :class="['h-7 rounded px-2 text-xs', discountType === 'flat' ? 'bg-primary text-primary-foreground' : 'hover:bg-accent']" @click="toggleDiscount('flat')">₹</button>
                                    <button type="button" :class="['h-7 rounded px-2 text-xs', discountType === 'percent' ? 'bg-primary text-primary-foreground' : 'hover:bg-accent']" @click="toggleDiscount('percent')">%</button>
                                </span>
                                <Input v-if="discountType" v-model="discountValue" type="number" min="0" step="any" inputmode="decimal" class="h-8 w-20 text-right" aria-label="Discount value" />
                            </dt>
                            <dd class="tabular-nums" :class="totals.discount_amount > 0 ? 'text-emerald-700 dark:text-emerald-400' : ''">
                                {{ totals.discount_amount > 0 ? '− ' + formatMoney(totals.discount_amount) : '—' }}
                            </dd>
                        </div>
                        <p v-if="errors.discount_value || errors.discount_type" class="text-xs text-destructive">{{ errors.discount_value || errors.discount_type }}</p>

                        <div v-if="tax_rate > 0" class="flex justify-between">
                            <dt class="text-muted-foreground">GST {{ tax_rate }}%</dt>
                            <dd class="tabular-nums">{{ formatMoney(totals.tax_amount) }}</dd>
                        </div>
                        <div v-if="totals.round_off !== 0" class="flex justify-between">
                            <dt class="text-muted-foreground">Round off</dt>
                            <dd class="tabular-nums">{{ totals.round_off > 0 ? '+' : '−' }} {{ formatMoney(Math.abs(totals.round_off)) }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between border-t pt-2">
                            <dt class="text-base font-semibold">Total</dt>
                            <dd class="text-3xl font-bold tabular-nums">{{ formatMoney(totals.total) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-3" data-error-anchor="payment_mode">
                        <div class="grid grid-cols-4 gap-1.5" role="group" aria-label="Payment mode">
                            <button
                                v-for="m in payment_modes"
                                :key="m"
                                type="button"
                                :class="['h-10 rounded-md border text-sm font-medium', paymentMode === m ? 'border-primary bg-primary text-primary-foreground' : 'bg-background hover:bg-accent']"
                                @click="paymentMode = m"
                            >
                                {{ paymentLabel(m) }}
                            </button>
                        </div>
                        <p v-if="errors.payment_mode" class="mt-1 text-xs text-destructive">{{ errors.payment_mode }}</p>
                        <div class="mt-2 flex items-center justify-between gap-2 text-sm">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" :checked="paymentStatus === 'paid'" @change="paymentStatus = paymentStatus === 'paid' ? 'unpaid' : 'paid'" />
                                <span>{{ paymentStatus === 'paid' ? 'Paid' : 'Unpaid — mark as pending' }}</span>
                            </label>
                            <button v-if="!showNotes" type="button" class="text-xs text-muted-foreground underline underline-offset-2 hover:text-foreground" @click="showNotes = true">Add note</button>
                        </div>
                    </div>

                    <div v-if="showNotes" class="mt-2">
                        <Input v-model="notes" placeholder="Internal note (optional)" class="h-9" />
                        <p v-if="errors.notes" class="text-xs text-destructive">{{ errors.notes }}</p>
                    </div>

                    <Button type="button" size="lg" class="mt-3 h-12 w-full text-base" :disabled="!canSave" @click="save">
                        <LoaderCircle v-if="saving" class="animate-spin" />
                        <Save v-else />
                        {{ isEditing ? 'Save changes' : 'Save & Preview' }}
                    </Button>
                    <p class="mt-1.5 text-center text-xs text-muted-foreground">Ctrl/⌘ + S to save · Enter in search adds first match</p>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
