<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

const page = usePage<SharedData>();
</script>

<template>
    <a
        :href="page.props.powered_by.url"
        target="_blank"
        rel="noopener"
        class="mx-2 mb-1 flex items-center gap-2 rounded-md px-2 py-1.5 text-[11px] text-sidebar-foreground/60 transition-colors hover:bg-sidebar-accent hover:text-sidebar-foreground group-data-[collapsible=icon]:hidden"
    >
        <span class="h-1.5 w-1.5 rounded-full bg-sidebar-primary"></span>
        <span class="truncate">{{ page.props.powered_by.label }}</span>
    </a>
</template>
