<script setup lang="ts">
import type { Paginated } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

defineProps<{
    paginator: Paginated<unknown>;
}>();
</script>

<template>
    <nav v-if="paginator.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 print:hidden" aria-label="Pagination">
        <p class="text-sm text-muted-foreground">Showing {{ paginator.from ?? 0 }}–{{ paginator.to ?? 0 }} of {{ paginator.total }}</p>
        <div class="flex items-center gap-1">
            <Link
                v-if="paginator.prev_page_url"
                :href="paginator.prev_page_url"
                preserve-scroll
                class="inline-flex h-8 w-8 items-center justify-center rounded-md border hover:bg-accent"
                aria-label="Previous page"
            >
                <ChevronLeft class="h-4 w-4" />
            </Link>
            <span v-else class="inline-flex h-8 w-8 items-center justify-center rounded-md border opacity-40"><ChevronLeft class="h-4 w-4" /></span>

            <span class="px-2 text-sm">Page {{ paginator.current_page }} of {{ paginator.last_page }}</span>

            <Link
                v-if="paginator.next_page_url"
                :href="paginator.next_page_url"
                preserve-scroll
                class="inline-flex h-8 w-8 items-center justify-center rounded-md border hover:bg-accent"
                aria-label="Next page"
            >
                <ChevronRight class="h-4 w-4" />
            </Link>
            <span v-else class="inline-flex h-8 w-8 items-center justify-center rounded-md border opacity-40"><ChevronRight class="h-4 w-4" /></span>
        </div>
    </nav>
</template>
