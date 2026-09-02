<script setup lang="ts">
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Scissors } from 'lucide-vue-next';

const page = usePage<SharedData>();
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
        <img v-if="page.props.salon.logo_url" :src="page.props.salon.logo_url" alt="" class="size-8 object-cover" />
        <Scissors v-else class="size-4" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-semibold leading-none">{{ page.props.salon.name }}</span>
    </div>
</template>
