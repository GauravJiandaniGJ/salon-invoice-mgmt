<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string; // YYYY-MM-DD or YYYY-MM
        type?: 'date' | 'month';
        min?: string;
        max?: string;
        class?: HTMLAttributes['class'];
        disabled?: boolean;
        id?: string;
    }>(),
    { type: 'date', disabled: false },
);

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>();
</script>

<template>
    <input
        :id="id"
        :type="type"
        :value="modelValue"
        :min="min"
        :max="max"
        :disabled="disabled"
        :class="
            cn(
                'flex h-10 rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                props.class,
            )
        "
        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
</template>
