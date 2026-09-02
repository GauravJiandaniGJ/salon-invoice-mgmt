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
        class="mx-2 mb-1 flex items-center gap-2 px-2 py-1.5 text-[11px] text-sidebar-foreground/70 transition-opacity hover:opacity-100 group-data-[collapsible=icon]:hidden"
    >
        <span>Powered by</span>
        <img src="/brand/todoit-logo.png" alt="TodoIT" class="h-5 w-auto" />
    </a>
</template>
