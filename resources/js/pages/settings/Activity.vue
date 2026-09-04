<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/dates';
import type { ActivityFilters, ActivityRow, BreadcrumbItem, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, Settings2 } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';

const props = defineProps<{
    filters: ActivityFilters;
    activities: Paginated<ActivityRow>;
    users: { id: number; name: string }[];
    actions: { value: string; label: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings' },
    { title: 'Activity', href: '/settings/activity' },
];

const form = reactive<ActivityFilters>({ ...props.filters });
const loading = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;

const apply = (debounce = 0) => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            '/settings/activity',
            { ...form },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => (loading.value = true),
                onFinish: () => (loading.value = false),
            },
        );
    }, debounce);
};

watch(
    () => form.q,
    () => apply(350),
);
watch([() => form.action, () => form.user_id, () => form.from, () => form.to], () => apply());

const reset = () => {
    Object.assign(form, { q: '', action: '', user_id: '', from: '', to: '' });
    apply();
};

const csvHref = () => `/settings/activity.csv?${new URLSearchParams({ ...form }).toString()}`;

/** "price: 225 → 250" for the small grey line under a row. */
const changeSummary = (changes: ActivityRow['changes']): string => {
    if (!changes) return '';
    const from = (changes as Record<string, Record<string, unknown>>).from;
    const to = (changes as Record<string, Record<string, unknown>>).to;
    if (from && to) {
        return Object.keys(to)
            .filter((k) => String(from[k] ?? '') !== String(to[k] ?? ''))
            .map((k) => `${k}: ${from[k] ?? '—'} → ${to[k] ?? '—'}`)
            .join(' · ');
    }
    return Object.entries(changes)
        .map(([k, v]) => `${k}: ${typeof v === 'object' ? JSON.stringify(v) : v}`)
        .join(' · ');
};

const linkFor = (row: ActivityRow): string | null => {
    if (row.subject_type === 'Invoice' && row.subject_id) return `/invoices/${row.subject_id}`;
    if (row.subject_type === 'Customer' && row.subject_id) return `/customers/${row.subject_id}`;
    return null;
};

const toneFor = (action: string): string => {
    if (action.includes('deleted') || action.includes('voided') || action.includes('failed')) return 'text-destructive';
    if (action.includes('created')) return 'text-green-700 dark:text-green-400';
    return '';
};
</script>

<template>
    <Head title="Activity" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Activity</h1>
                    <p class="text-sm text-muted-foreground">Who did what, kept for 12 months. Bills are never deleted, only voided.</p>
                </div>
                <div class="flex gap-2">
                    <Button as-child variant="outline" size="sm"
                        ><Link href="/settings"><Settings2 /> Settings</Link></Button
                    >
                    <Button as-child variant="ghost" size="sm"><a :href="csvHref()">CSV</a></Button>
                </div>
            </div>

            <div class="grid gap-2 rounded-xl border bg-card p-3 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
                <div class="relative sm:col-span-2 lg:col-span-1">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="form.q" placeholder="Search" class="h-9 pl-9" />
                </div>
                <select v-model="form.action" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                    <option value="">All actions</option>
                    <option v-for="a in actions" :key="a.value" :value="a.value">{{ a.label }}</option>
                </select>
                <select v-model="form.user_id" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                    <option value="">Everyone</option>
                    <option v-for="u in users" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                </select>
                <input v-model="form.from" type="date" class="h-9 rounded-md border border-input bg-background px-2 text-sm" aria-label="From" />
                <div class="flex gap-2">
                    <input
                        v-model="form.to"
                        type="date"
                        class="h-9 flex-1 rounded-md border border-input bg-background px-2 text-sm"
                        aria-label="To"
                    />
                    <Button variant="ghost" size="sm" class="h-9" @click="reset">Clear</Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card shadow-sm" :class="{ 'opacity-60': loading }">
                <EmptyState
                    v-if="activities.data.length === 0"
                    title="Nothing here yet"
                    description="Actions will appear as the team uses the app."
                    class="m-4"
                />
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">When</th>
                                <th class="px-4 py-2 font-medium">Who</th>
                                <th class="px-4 py-2 font-medium">Action</th>
                                <th class="px-4 py-2 font-medium">Item</th>
                                <th class="px-4 py-2 font-medium">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in activities.data" :key="row.id" class="border-t align-top">
                                <td class="whitespace-nowrap px-4 py-2 text-muted-foreground">{{ formatDateTime(row.created_at) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 font-medium">{{ row.user_name }}</td>
                                <td class="whitespace-nowrap px-4 py-2" :class="toneFor(row.action)">{{ row.action_label }}</td>
                                <td class="px-4 py-2">
                                    <Link v-if="linkFor(row)" :href="linkFor(row)!" class="font-medium hover:underline">{{
                                        row.subject_label ?? '—'
                                    }}</Link>
                                    <span v-else>{{ row.subject_label ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-2 text-muted-foreground">
                                    {{ row.description }}
                                    <div v-if="changeSummary(row.changes)" class="text-xs">{{ changeSummary(row.changes) }}</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination :paginator="activities" class="border-t px-4 py-3" />
            </div>
        </div>
    </AppLayout>
</template>
