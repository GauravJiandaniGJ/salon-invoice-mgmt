<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: number | string | null;
        min?: number;
        max?: number;
        step?: string;
        placeholder?: string;
        class?: HTMLAttributes['class'];
        disabled?: boolean;
        id?: string;
    }>(),
    { min: 0, step: 'any', placeholder: '0', disabled: false },
);

const emit = defineEmits<{ (e: 'update:modelValue', value: number | null): void }>();

const onInput = (e: Event) => {
    const raw = (e.target as HTMLInputElement).value;
    emit('update:modelValue', raw === '' ? null : Number(raw));
};
</script>

<template>
    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-muted-foreground">₹</span>
        <input
            :id="id"
            type="number"
            inputmode="decimal"
            :value="modelValue ?? ''"
            :min="min"
            :max="max"
            :step="step"
            :placeholder="placeholder"
            :disabled="disabled"
            :class="
                cn(
                    'flex h-10 w-full rounded-md border border-input bg-background py-2 pl-7 pr-3 text-right text-base tabular-nums ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                    props.class,
                )
            "
            @input="onInput"
        />
    </div>
</template>
