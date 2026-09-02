<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

defineProps<{
    paginator: {
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
}>();

const isNumeric = (label: string) => /^\d+$/.test(label);
</script>

<template>
    <nav v-if="paginator.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="Pagination">
        <p class="text-muted-foreground">Showing {{ paginator.from ?? 0 }}–{{ paginator.to ?? 0 }} of {{ paginator.total }}</p>
        <div class="flex items-center gap-1">
            <Link
                :href="paginator.prev_page_url ?? '#'"
                preserve-scroll
                :class="['inline-flex h-9 items-center rounded-md border px-2', !paginator.prev_page_url && 'pointer-events-none opacity-40']"
                aria-label="Previous page"
            >
                <ChevronLeft class="h-4 w-4" />
            </Link>
            <template v-for="link in paginator.links" :key="link.label">
                <Link
                    v-if="isNumeric(link.label) && link.url"
                    :href="link.url"
                    preserve-scroll
                    :class="[
                        'inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-2',
                        link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-accent',
                    ]"
                >
                    {{ link.label }}
                </Link>
                <span v-else-if="link.label === '...'" class="px-1 text-muted-foreground">…</span>
            </template>
            <Link
                :href="paginator.next_page_url ?? '#'"
                preserve-scroll
                :class="['inline-flex h-9 items-center rounded-md border px-2', !paginator.next_page_url && 'pointer-events-none opacity-40']"
                aria-label="Next page"
            >
                <ChevronRight class="h-4 w-4" />
            </Link>
        </div>
    </nav>
</template>
