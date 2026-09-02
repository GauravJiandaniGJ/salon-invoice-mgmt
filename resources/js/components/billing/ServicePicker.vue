<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/lib/money';
import type { Audience, CatalogCategory, CatalogService } from '@/types';
import { Plus, Search, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps<{
    catalog: CatalogCategory[];
}>();

const audience = defineModel<Audience>('audience', { required: true });

const emit = defineEmits<{
    (e: 'add', service: CatalogService, price: number): void;
    (e: 'add-custom', description: string, price: number): void;
}>();

const search = ref('');
const searchInput = ref<InstanceType<typeof Input> | null>(null);
const selectedCategoryId = ref<number | null>(null);

interface PickerService extends CatalogService {
    category_id: number;
    category_name: string;
    haystack: string;
}

const audienceCategories = computed(() =>
    props.catalog.filter((c) => audience.value === 'all' || c.audience === 'all' || c.audience === audience.value),
);

watch(audienceCategories, (cats) => {
    if (selectedCategoryId.value !== null && !cats.some((c) => c.id === selectedCategoryId.value)) selectedCategoryId.value = null;
});

const allServices = computed<PickerService[]>(() =>
    audienceCategories.value.flatMap((c) =>
        c.services.map((s) => ({
            ...s,
            category_id: c.id,
            category_name: c.name,
            haystack: `${s.group_name ?? ''} ${s.name} ${c.name}`.toLowerCase(),
        })),
    ),
);

const totalCount = computed(() => allServices.value.length);

const isSearching = computed(() => search.value.trim().length >= 2);

const visible = computed<PickerService[]>(() => {
    if (isSearching.value) {
        const terms = search.value.trim().toLowerCase().split(/\s+/);
        return allServices.value.filter((s) => terms.every((t) => s.haystack.includes(t)));
    }
    if (selectedCategoryId.value === null) return allServices.value;
    return allServices.value.filter((s) => s.category_id === selectedCategoryId.value);
});

/** Group visible services by category → group_name for display. */
const grouped = computed(() => {
    const byCategory = new Map<number, { name: string; groups: Map<string, PickerService[]> }>();
    for (const s of visible.value) {
        if (!byCategory.has(s.category_id)) byCategory.set(s.category_id, { name: s.category_name, groups: new Map() });
        const cat = byCategory.get(s.category_id)!;
        const key = s.group_name ?? '';
        if (!cat.groups.has(key)) cat.groups.set(key, []);
        cat.groups.get(key)!.push(s);
    }
    return [...byCategory.entries()].map(([id, c]) => ({ id, name: c.name, groups: [...c.groups.entries()].map(([g, services]) => ({ group: g, services })) }));
});

const gridEl = ref<HTMLElement | null>(null);
const selectCategory = (id: number | null) => {
    selectedCategoryId.value = id;
    gridEl.value?.scrollTo({ top: 0 });
};

// ----- price prompt for ranged / zero-priced services -----
const pending = ref<{ service: PickerService; price: string } | null>(null);
const pendingInput = ref<InstanceType<typeof Input> | null>(null);

const needsPrice = (s: CatalogService) => s.price_max !== null || Number(s.price) <= 0;

const pick = async (s: PickerService) => {
    if (needsPrice(s)) {
        pending.value = { service: s, price: Number(s.price) > 0 ? String(s.price) : '' };
        await nextTick();
        (pendingInput.value?.$el as HTMLInputElement | undefined)?.focus();
        return;
    }
    emit('add', s, Number(s.price));
};

const confirmPending = () => {
    if (!pending.value) return;
    const price = parseFloat(pending.value.price);
    if (!Number.isFinite(price) || price < 0) return;
    emit('add', pending.value.service, price);
    pending.value = null;
    focusSearch();
};

const onSearchEnter = () => {
    const first = visible.value[0];
    if (!first) return;
    pick(first);
    if (!needsPrice(first)) search.value = '';
};

const focusSearch = () => (searchInput.value?.$el as HTMLInputElement | undefined)?.focus();

// ----- custom line -----
const showCustom = ref(false);
const custom = ref({ description: '', price: '' });
const customDesc = ref<InstanceType<typeof Input> | null>(null);

const openCustom = async () => {
    showCustom.value = true;
    await nextTick();
    (customDesc.value?.$el as HTMLInputElement | undefined)?.focus();
};

const addCustom = () => {
    const price = parseFloat(custom.value.price);
    if (!custom.value.description.trim() || !Number.isFinite(price) || price < 0) return;
    emit('add-custom', custom.value.description.trim(), price);
    custom.value = { description: '', price: '' };
    showCustom.value = false;
    focusSearch();
};

const priceLabel = (s: CatalogService) => (s.price_max !== null ? `${formatMoney(s.price)}–${formatMoney(s.price_max).replace('₹', '')}` : Number(s.price) > 0 ? formatMoney(s.price) : 'Price at billing');

const audienceLabel: Record<Audience, string> = { women: 'Women', men: 'Men', all: 'All' };

defineExpose({ focusSearch });
</script>

<template>
    <section class="flex min-h-0 flex-1 flex-col rounded-xl border bg-card shadow-sm">
        <!-- header: search · audience · custom line -->
        <div class="flex flex-wrap items-center gap-2 border-b p-3">
            <div class="relative min-w-[200px] flex-1">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    ref="searchInput"
                    v-model="search"
                    placeholder="Search services… (Enter adds first match)"
                    class="h-11 pl-9 text-base"
                    autocomplete="off"
                    @keydown.enter.prevent="onSearchEnter"
                    @keydown.esc="search = ''"
                />
                <button v-if="search" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 hover:bg-accent" aria-label="Clear search" @click="search = ''; focusSearch()">
                    <X class="h-4 w-4" />
                </button>
            </div>
            <div class="flex rounded-md border bg-background p-0.5" role="group" aria-label="Audience">
                <button
                    v-for="a in ['women', 'men', 'all'] as Audience[]"
                    :key="a"
                    type="button"
                    :class="['h-9 rounded px-3 text-sm font-medium', audience === a ? 'bg-primary text-primary-foreground' : 'hover:bg-accent']"
                    @click="audience = a"
                >
                    {{ audienceLabel[a] }}
                </button>
            </div>
            <Button type="button" variant="outline" class="h-10" @click="openCustom"><Plus /> Custom line</Button>
        </div>

        <form v-if="showCustom" class="flex flex-wrap items-end gap-2 border-b bg-muted/40 p-3" @submit.prevent="addCustom">
            <div class="grid min-w-[220px] flex-1 gap-1">
                <label class="text-xs font-medium" for="custom-desc">Description</label>
                <Input id="custom-desc" ref="customDesc" v-model="custom.description" placeholder="e.g. Hair accessories" class="h-10" />
            </div>
            <div class="grid gap-1">
                <label class="text-xs font-medium" for="custom-price">Price ₹</label>
                <Input id="custom-price" v-model="custom.price" type="number" min="0" step="any" inputmode="decimal" class="h-10 w-28" @keydown.enter.prevent="addCustom" />
            </div>
            <Button type="submit" class="h-10">Add</Button>
            <Button type="button" variant="ghost" class="h-10" @click="showCustom = false">Cancel</Button>
        </form>

        <div v-if="pending" class="flex flex-wrap items-end gap-2 border-b bg-amber-50 p-3 dark:bg-amber-950/40">
            <div class="flex-1 text-sm">
                <p class="font-medium">{{ pending.service.display_name }}</p>
                <p class="text-muted-foreground">{{ pending.service.price_max !== null ? `Range ${priceLabel(pending.service)} — enter the price for this bill` : 'Enter the price for this bill' }}</p>
            </div>
            <div class="grid gap-1">
                <label class="text-xs font-medium" for="pending-price">Price ₹</label>
                <Input id="pending-price" ref="pendingInput" v-model="pending.price" type="number" min="0" step="any" inputmode="decimal" class="h-10 w-28" @keydown.enter.prevent="confirmPending" @keydown.esc="pending = null" />
            </div>
            <Button type="button" class="h-10" @click="confirmPending">Add</Button>
            <Button type="button" variant="ghost" class="h-10" @click="pending = null">Cancel</Button>
        </div>

        <!-- body: category list (left) · services grid (right) -->
        <div class="flex min-h-0 flex-1">
            <nav
                class="hidden w-52 shrink-0 flex-col overflow-y-auto border-r bg-muted/30 py-1.5 lg:flex"
                :class="{ 'pointer-events-none opacity-40': isSearching }"
                aria-label="Service categories"
            >
                <button
                    type="button"
                    :class="[
                        'mx-1.5 flex items-center justify-between rounded-md px-3 py-2 text-left text-sm',
                        selectedCategoryId === null && !isSearching ? 'bg-primary font-medium text-primary-foreground' : 'hover:bg-accent',
                    ]"
                    @click="selectCategory(null)"
                >
                    <span>All services</span>
                    <span class="text-xs opacity-70">{{ totalCount }}</span>
                </button>
                <button
                    v-for="c in audienceCategories"
                    :key="c.id"
                    type="button"
                    :class="[
                        'mx-1.5 flex items-center justify-between gap-2 rounded-md px-3 py-2 text-left text-sm',
                        selectedCategoryId === c.id && !isSearching ? 'bg-primary font-medium text-primary-foreground' : 'hover:bg-accent',
                    ]"
                    @click="selectCategory(c.id)"
                >
                    <span class="line-clamp-2 leading-tight">{{ c.name }}</span>
                    <span class="shrink-0 text-xs opacity-70">{{ c.services.length }}</span>
                </button>
            </nav>

            <div class="flex min-h-0 flex-1 flex-col">
                <div class="border-b p-2 lg:hidden">
                    <select
                        :value="selectedCategoryId ?? ''"
                        :disabled="isSearching"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                        aria-label="Category"
                        @change="(e) => selectCategory((e.target as HTMLSelectElement).value === '' ? null : Number((e.target as HTMLSelectElement).value))"
                    >
                        <option value="">All services ({{ totalCount }})</option>
                        <option v-for="c in audienceCategories" :key="c.id" :value="c.id">{{ c.name }} ({{ c.services.length }})</option>
                    </select>
                </div>

                <div ref="gridEl" class="min-h-0 flex-1 overflow-y-auto p-3">
                    <div v-if="catalog.length === 0" class="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
                        No services in the catalog yet. The owner can add them under Services.
                    </div>
                    <div v-else-if="visible.length === 0" class="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
                        No services match “{{ search }}”. Try another word or add a custom line.
                    </div>

                    <div v-for="cat in grouped" :key="cat.id" class="mb-4">
                        <h3 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ cat.name }}</h3>
                        <div v-for="g in cat.groups" :key="g.group" class="mb-2">
                            <p v-if="g.group" class="mb-1 text-xs font-medium text-muted-foreground">{{ g.group }}</p>
                            <div class="grid grid-cols-2 gap-1.5 md:grid-cols-3 2xl:grid-cols-4">
                                <button
                                    v-for="s in g.services"
                                    :key="s.id"
                                    type="button"
                                    class="flex min-h-[60px] flex-col items-start justify-between rounded-md border bg-background p-2 text-left shadow-sm transition-colors hover:border-primary hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring active:scale-[0.99]"
                                    :title="s.description ?? undefined"
                                    @click="pick(s)"
                                >
                                    <span class="line-clamp-2 text-sm font-medium leading-tight">{{ s.name }}</span>
                                    <span class="mt-1 flex w-full items-center justify-between gap-2 text-xs text-muted-foreground">
                                        <span class="font-semibold text-foreground">{{ priceLabel(s) }}</span>
                                        <span v-if="s.duration_minutes">{{ s.duration_minutes }} min</span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
