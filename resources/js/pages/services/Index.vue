<script setup lang="ts">
import InlineEdit from '@/components/InlineEdit.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ChevronDown, ChevronRight, Plus, Trash2 } from 'lucide-vue-next';
import { reactive, ref } from 'vue';

interface Service {
    id: number;
    group_name: string | null;
    name: string;
    description: string | null;
    price: number;
    price_max: number | null;
    duration_minutes: number | null;
    is_active: boolean;
    sort_order: number;
    can_delete: boolean;
}

interface Category {
    id: number;
    name: string;
    audience: string;
    is_active: boolean;
    sort_order: number;
    services: Service[];
}

const props = defineProps<{
    categories: Category[];
    audiences: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Services', href: '/services' }];

const opts = { preserveScroll: true, preserveState: true };

// ----- categories -----
const collapsed = reactive<Record<number, boolean>>({});
const toggle = (id: number) => (collapsed[id] = !collapsed[id]);

const showAddCategory = ref(false);
const categoryForm = useForm({ name: '', audience: 'all' });
const addCategory = () => {
    categoryForm.post('/service-categories', {
        ...opts,
        onSuccess: () => {
            categoryForm.reset();
            showAddCategory.value = false;
        },
    });
};

const updateCategory = (category: Category, data: Partial<Category>) => {
    router.patch(`/service-categories/${category.id}`, data, opts);
};

const moveCategory = (index: number, direction: -1 | 1) => {
    const ids = props.categories.map((c) => c.id);
    const target = index + direction;
    if (target < 0 || target >= ids.length) return;
    [ids[index], ids[target]] = [ids[target], ids[index]];
    router.post('/service-categories/reorder', { ids }, opts);
};

const deleteCategory = (category: Category) => {
    if (!confirm(`Delete category "${category.name}"?`)) return;
    router.delete(`/service-categories/${category.id}`, opts);
};

// ----- services -----
const updateService = (service: Service, data: Record<string, unknown>) => {
    router.patch(`/services/${service.id}`, data, opts);
};

const moveService = (category: Category, index: number, direction: -1 | 1) => {
    const ids = category.services.map((s) => s.id);
    const target = index + direction;
    if (target < 0 || target >= ids.length) return;
    [ids[index], ids[target]] = [ids[target], ids[index]];
    router.post('/services/reorder', { ids }, opts);
};

const deleteService = (service: Service) => {
    if (!confirm(`Delete "${service.name}"?`)) return;
    router.delete(`/services/${service.id}`, opts);
};

const addingIn = ref<number | null>(null);
const serviceForm = useForm({
    service_category_id: 0,
    group_name: '',
    name: '',
    price: '' as string | number,
    price_max: '' as string | number,
    duration_minutes: '' as string | number,
});
const startAddService = (category: Category) => {
    serviceForm.reset();
    serviceForm.clearErrors();
    serviceForm.service_category_id = category.id;
    addingIn.value = category.id;
    collapsed[category.id] = false;
};
const addService = () => {
    serviceForm.post('/services', {
        ...opts,
        onSuccess: () => {
            const keep = serviceForm.service_category_id;
            serviceForm.reset();
            serviceForm.service_category_id = keep; // stay in "add" mode for quick multi-entry
        },
    });
};

const audienceLabel: Record<string, string> = { women: 'Women', men: 'Men', all: 'Everyone' };
</script>

<template>
    <Head title="Services" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Services & prices</h1>
                    <p class="text-sm text-muted-foreground">Click any name or price to edit it. Enter saves, Esc cancels.</p>
                </div>
                <Button @click="showAddCategory = !showAddCategory"><Plus /> Add category</Button>
            </div>

            <form v-if="showAddCategory" class="flex flex-wrap items-end gap-3 rounded-xl border bg-card shadow-sm p-4" @submit.prevent="addCategory">
                <div class="grid flex-1 gap-1">
                    <label class="text-xs font-medium" for="new-cat-name">Category name</label>
                    <Input id="new-cat-name" v-model="categoryForm.name" placeholder="e.g. Hair Spa" autofocus />
                    <p v-if="categoryForm.errors.name" class="text-xs text-destructive">{{ categoryForm.errors.name }}</p>
                </div>
                <div class="grid gap-1">
                    <label class="text-xs font-medium" for="new-cat-aud">For</label>
                    <select id="new-cat-aud" v-model="categoryForm.audience" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                        <option v-for="a in audiences" :key="a" :value="a">{{ audienceLabel[a] }}</option>
                    </select>
                </div>
                <Button type="submit" :disabled="categoryForm.processing">Save</Button>
                <Button type="button" variant="ghost" @click="showAddCategory = false">Cancel</Button>
            </form>

            <div v-if="categories.length === 0" class="rounded-xl border border-dashed bg-card/60 p-10 text-center text-muted-foreground">
                No services yet. Add a category to get started.
            </div>

            <section v-for="(category, ci) in categories" :key="category.id" class="rounded-xl border bg-card shadow-sm" :class="{ 'opacity-60': !category.is_active }">
                <header class="flex flex-wrap items-center gap-2 border-b px-3 py-2">
                    <button type="button" class="rounded p-1 hover:bg-accent" :aria-label="collapsed[category.id] ? 'Expand' : 'Collapse'" @click="toggle(category.id)">
                        <ChevronRight v-if="collapsed[category.id]" class="h-4 w-4" />
                        <ChevronDown v-else class="h-4 w-4" />
                    </button>

                    <div class="min-w-[200px] flex-1 font-semibold">
                        <InlineEdit :model-value="category.name" @save="(v) => updateCategory(category, { name: String(v ?? '') })" />
                    </div>

                    <select
                        :value="category.audience"
                        class="h-8 rounded-md border border-input bg-background px-2 text-xs"
                        title="Who is this category for?"
                        @change="(e) => updateCategory(category, { audience: (e.target as HTMLSelectElement).value })"
                    >
                        <option v-for="a in audiences" :key="a" :value="a">{{ audienceLabel[a] }}</option>
                    </select>

                    <label class="flex items-center gap-1 text-xs">
                        <input type="checkbox" :checked="category.is_active" @change="updateCategory(category, { is_active: !category.is_active })" />
                        Active
                    </label>

                    <span class="text-xs text-muted-foreground">{{ category.services.length }} services</span>

                    <div class="flex items-center">
                        <Button variant="ghost" size="icon" class="h-8 w-8" :disabled="ci === 0" title="Move up" @click="moveCategory(ci, -1)"><ArrowUp /></Button>
                        <Button variant="ghost" size="icon" class="h-8 w-8" :disabled="ci === categories.length - 1" title="Move down" @click="moveCategory(ci, 1)"><ArrowDown /></Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 text-destructive"
                            :disabled="category.services.length > 0"
                            :title="category.services.length > 0 ? 'Delete its services first' : 'Delete category'"
                            @click="deleteCategory(category)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </header>

                <div v-show="!collapsed[category.id]" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left dark:bg-gray-900/40 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="border-r border-gray-200 dark:border-gray-800 px-3 py-2.5 font-medium">Group</th>
                                <th class="border-r border-gray-200 dark:border-gray-800 px-3 py-2.5 font-medium">Service</th>
                                <th class="w-[130px] border-r border-gray-200 dark:border-gray-800 px-3 py-2.5 text-right font-medium">Price (₹)</th>
                                <th class="w-[130px] border-r border-gray-200 dark:border-gray-800 px-3 py-2.5 text-right font-medium" title="Only for services priced as a range, e.g. Nail art ₹100–500">Up to (₹)</th>
                                <th class="w-[90px] border-r border-gray-200 dark:border-gray-800 px-3 py-2.5 text-center font-medium">Active</th>
                                <th class="w-[120px] px-3 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(service, si) in category.services" :key="service.id" class="border-t border-gray-200 dark:border-gray-800 hover:bg-gray-50/60 dark:hover:bg-gray-900/40" :class="{ 'text-muted-foreground': !service.is_active }">
                                <td class="w-[220px] border-r border-gray-200 dark:border-gray-800 px-2 py-1 align-middle">
                                    <InlineEdit :model-value="service.group_name" placeholder="—" @save="(v) => updateService(service, { group_name: v })" />
                                </td>
                                <td class="border-r border-gray-200 dark:border-gray-800 px-2 py-1 align-middle">
                                    <InlineEdit :model-value="service.name" @save="(v) => updateService(service, { name: v })" />
                                    <p v-if="service.description" class="px-2 text-xs text-muted-foreground">{{ service.description }}</p>
                                </td>
                                <td class="border-r border-gray-200 dark:border-gray-800 px-2 py-1 text-right align-middle">
                                    <InlineEdit
                                        type="number"
                                        :min="0"
                                        :model-value="service.price"
                                        :display="formatMoney(service.price)"
                                        input-class="w-24 text-right" display-class="text-right"
                                        @save="(v) => updateService(service, { price: v })"
                                    />
                                </td>
                                <td class="border-r border-gray-200 dark:border-gray-800 px-2 py-1 text-right align-middle">
                                    <InlineEdit
                                        type="number"
                                        :min="0"
                                        :model-value="service.price_max"
                                        :display="service.price_max == null ? undefined : formatMoney(service.price_max)"
                                        input-class="w-24 text-right" display-class="text-right"
                                        @save="(v) => updateService(service, { price_max: v })"
                                    />
                                </td>
                                <td class="border-r border-gray-200 dark:border-gray-800 px-3 py-1 text-center align-middle">
                                    <input type="checkbox" :checked="service.is_active" @change="updateService(service, { is_active: !service.is_active })" />
                                </td>
                                <td class="whitespace-nowrap px-2 py-1 text-right align-middle">
                                    <Button variant="ghost" size="icon" class="h-7 w-7" :disabled="si === 0" title="Move up" @click="moveService(category, si, -1)"><ArrowUp /></Button>
                                    <Button variant="ghost" size="icon" class="h-7 w-7" :disabled="si === category.services.length - 1" title="Move down" @click="moveService(category, si, 1)"><ArrowDown /></Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-7 w-7 text-destructive"
                                        :disabled="!service.can_delete"
                                        :title="service.can_delete ? 'Delete' : 'Already billed — deactivate instead'"
                                        @click="deleteService(service)"
                                    >
                                        <Trash2 />
                                    </Button>
                                </td>
                            </tr>

                            <tr v-if="addingIn === category.id" class="border-t bg-muted/40">
                                <td colspan="6" class="px-3 py-2">
                                    <form class="flex flex-wrap items-start gap-2" @submit.prevent="addService">
                                        <div class="grid gap-1">
                                            <Input v-model="serviceForm.group_name" placeholder="Group (optional)" class="h-9 w-44" />
                                        </div>
                                        <div class="grid flex-1 gap-1">
                                            <Input v-model="serviceForm.name" placeholder="Service name" class="h-9 min-w-48" autofocus />
                                            <p v-if="serviceForm.errors.name" class="text-xs text-destructive">{{ serviceForm.errors.name }}</p>
                                        </div>
                                        <div class="grid gap-1">
                                            <Input v-model="serviceForm.price" type="number" min="0" step="any" placeholder="Price ₹" class="h-9 w-28" />
                                            <p v-if="serviceForm.errors.price" class="text-xs text-destructive">{{ serviceForm.errors.price }}</p>
                                        </div>
                                        <div class="grid gap-1">
                                            <Input v-model="serviceForm.price_max" type="number" min="0" step="any" placeholder="Max ₹" class="h-9 w-24" />
                                            <p v-if="serviceForm.errors.price_max" class="text-xs text-destructive">{{ serviceForm.errors.price_max }}</p>
                                        </div>
                                        <Input v-model="serviceForm.duration_minutes" type="number" min="0" step="1" placeholder="Mins" class="h-9 w-20" />
                                        <Button type="submit" size="sm" class="h-9" :disabled="serviceForm.processing">Add</Button>
                                        <Button type="button" size="sm" variant="ghost" class="h-9" @click="addingIn = null">Done</Button>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="addingIn !== category.id" class="border-t px-3 py-2">
                        <Button variant="ghost" size="sm" @click="startAddService(category)"><Plus /> Add service</Button>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
