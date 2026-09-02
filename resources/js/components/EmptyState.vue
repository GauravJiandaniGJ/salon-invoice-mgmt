<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next';
import { Inbox } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: LucideIcon;
    }>(),
    { description: '', icon: Inbox },
);
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-lg border border-dashed px-6 py-10 text-center">
        <component :is="icon" class="mb-3 h-8 w-8 text-muted-foreground" />
        <p class="font-medium">{{ title }}</p>
        <p v-if="description" class="mt-1 max-w-sm text-sm text-muted-foreground">{{ description }}</p>
        <div v-if="$slots.default" class="mt-4">
            <slot />
        </div>
    </div>
</template>
