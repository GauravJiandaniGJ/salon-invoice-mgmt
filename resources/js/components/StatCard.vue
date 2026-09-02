<script setup lang="ts">
withDefaults(
    defineProps<{
        label: string;
        value: string;
        hint?: string;
        tone?: 'default' | 'positive' | 'negative' | 'muted';
    }>(),
    { hint: '', tone: 'default' },
);

const toneClass: Record<string, string> = {
    default: '',
    positive: 'text-green-700 dark:text-green-400',
    negative: 'text-red-700 dark:text-red-400',
    muted: 'text-muted-foreground',
};
</script>

<template>
    <div class="rounded-xl border bg-card shadow-sm p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ label }}</p>
        <p class="mt-1 text-2xl font-semibold tabular-nums" :class="toneClass[tone]">{{ value }}</p>
        <p v-if="hint" class="mt-1 text-xs text-muted-foreground">{{ hint }}</p>
        <slot />
    </div>
</template>
