<script setup lang="ts">
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<SharedData>();
const logo = computed(() => page.props.salon.logo_url || '/brand/wow-logo.png');
</script>

<template>
    <div class="flex aspect-square size-9 shrink-0 items-center justify-center overflow-hidden rounded-md bg-black ring-1 ring-sidebar-border">
        <img :src="logo" alt="" class="size-9 object-cover" />
    </div>
    <div class="ml-1 grid flex-1 text-left">
        <span class="truncate font-display text-[15px] font-semibold tracking-wide text-sidebar-foreground">{{ page.props.salon.name }}</span>
        <span class="truncate text-[10px] uppercase tracking-[0.18em] text-muted-foreground">Billing</span>
    </div>
</template>
