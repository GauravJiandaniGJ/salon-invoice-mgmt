<script setup lang="ts">
import { formatDate } from '@/components/billing/format';
import PaginationLinks from '@/components/billing/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/money';
import type { BreadcrumbItem, CustomerRow, Paginated } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { FilePlus2, Search, UserPlus } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    filters: { q: string };
    customers: Paginated<CustomerRow>;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Customers', href: '/customers' }];

const q = ref(props.filters.q ?? '');
const loading = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;

watch(q, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/customers', { q: q.value }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        });
    }, 350);
});

const genderLabel: Record<string, string> = { female: 'F', male: 'M', other: 'O' };

const showAdd = ref(false);
const addForm = useForm({ name: '', phone: '', gender: '' as '' | 'female' | 'male' | 'other', notes: '' });
const submitAdd = () => {
    addForm
        .transform((d) => ({ ...d, gender: d.gender || null, notes: d.notes || null }))
        .post('/customers', {
            preserveScroll: true,
            onSuccess: () => {
                addForm.reset();
                showAdd.value = false;
            },
        });
};
</script>

<template>
    <Head title="Customers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold">Customers</h1>
                <div class="flex gap-2">
                    <Button variant="outline" @click="showAdd = !showAdd"><UserPlus /> Add customer</Button>
                    <Button as-child>
                        <Link href="/bills/new"><FilePlus2 /> New Bill</Link>
                    </Button>
                </div>
            </div>

            <form v-if="showAdd" class="grid gap-3 rounded-xl border bg-card p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="submitAdd">
                <div class="grid gap-1">
                    <label class="text-xs font-medium" for="add-name">Name</label>
                    <Input id="add-name" v-model="addForm.name" placeholder="Full name" autofocus />
                    <p v-if="addForm.errors.name" class="text-xs text-destructive">{{ addForm.errors.name }}</p>
                </div>
                <div class="grid gap-1">
                    <label class="text-xs font-medium" for="add-phone">Phone</label>
                    <Input id="add-phone" v-model="addForm.phone" type="text" inputmode="tel" placeholder="98765 43210" />
                    <p v-if="addForm.errors.phone" class="text-xs text-destructive">{{ addForm.errors.phone }}</p>
                </div>
                <div class="grid gap-1">
                    <label class="text-xs font-medium" for="add-gender">Gender (optional)</label>
                    <select id="add-gender" v-model="addForm.gender" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">—</option>
                        <option value="female">Female</option>
                        <option value="male">Male</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="grid gap-1">
                    <label class="text-xs font-medium" for="add-notes">Notes (optional)</label>
                    <Input id="add-notes" v-model="addForm.notes" placeholder="Allergies, preferences" />
                </div>
                <div class="flex gap-2 sm:col-span-2 lg:col-span-4">
                    <Button type="submit" :disabled="addForm.processing">Save customer</Button>
                    <Button type="button" variant="ghost" @click="showAdd = false">Cancel</Button>
                </div>
            </form>

            <div class="relative max-w-md">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="q" placeholder="Search by name or phone" class="h-10 pl-9" autofocus />
            </div>

            <div class="overflow-x-auto rounded-xl border bg-card shadow-sm" :class="{ 'opacity-60': loading }">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">Name</th>
                            <th class="px-3 py-2 font-medium">Phone</th>
                            <th class="px-3 py-2 text-right font-medium">Visits</th>
                            <th class="px-3 py-2 text-right font-medium">Total spent</th>
                            <th class="px-3 py-2 font-medium">Last visit</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="customers.data.length === 0">
                            <td colspan="6" class="px-3 py-10 text-center text-muted-foreground">
                                {{ q ? `No customers match “${q}”.` : 'No customers yet — they are created automatically with the first bill.' }}
                            </td>
                        </tr>
                        <tr v-for="c in customers.data" :key="c.id" class="border-t hover:bg-accent/40">
                            <td class="px-3 py-2 font-medium">
                                <Link :href="`/customers/${c.id}`" class="hover:underline">{{ c.name }}</Link>
                                <span v-if="c.gender" class="ml-1 text-xs text-muted-foreground">({{ genderLabel[c.gender] }})</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">{{ c.phone_display }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ c.visits }}</td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ formatMoney(c.total_spent) }}</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ formatDate(c.last_visit_at) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right">
                                <Button as-child size="sm" variant="outline">
                                    <Link :href="`/bills/new?customer_id=${c.id}`">New Bill</Link>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <PaginationLinks :paginator="customers" />
        </div>
    </AppLayout>
</template>
