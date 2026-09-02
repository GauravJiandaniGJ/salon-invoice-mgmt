<script setup lang="ts">
import { PAYMENT_MODES, paymentModeLabel } from '@/lib/format';
import type { PaymentMode } from '@/types';

withDefaults(
    defineProps<{
        modelValue: PaymentMode | '';
        modes?: PaymentMode[];
        allowAll?: boolean; // adds an "All" chip that maps to ''
        size?: 'sm' | 'md';
    }>(),
    { modes: () => PAYMENT_MODES, allowAll: false, size: 'md' },
);

const emit = defineEmits<{ (e: 'update:modelValue', value: PaymentMode | ''): void }>();
</script>

<template>
    <div class="inline-flex flex-wrap gap-1.5" role="radiogroup">
        <button
            v-if="allowAll"
            type="button"
            role="radio"
            :aria-checked="modelValue === ''"
            :class="[
                'rounded-full border font-medium transition-colors',
                size === 'sm' ? 'px-2.5 py-1 text-xs' : 'px-3.5 py-1.5 text-sm',
                modelValue === '' ? 'border-primary bg-primary text-primary-foreground' : 'bg-background hover:bg-accent',
            ]"
            @click="emit('update:modelValue', '')"
        >
            All
        </button>
        <button
            v-for="mode in modes"
            :key="mode"
            type="button"
            role="radio"
            :aria-checked="modelValue === mode"
            :class="[
                'rounded-full border font-medium transition-colors',
                size === 'sm' ? 'px-2.5 py-1 text-xs' : 'px-3.5 py-1.5 text-sm',
                modelValue === mode ? 'border-primary bg-primary text-primary-foreground' : 'bg-background hover:bg-accent',
            ]"
            @click="emit('update:modelValue', mode)"
        >
            {{ paymentModeLabel(mode) }}
        </button>
    </div>
</template>
