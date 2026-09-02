<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string | number | null;
        type?: 'text' | 'number';
        placeholder?: string;
        display?: string; // optional pre-formatted display text
        inputClass?: string;
        min?: number;
        step?: string;
        disabled?: boolean;
    }>(),
    { type: 'text', placeholder: '—', inputClass: '', step: 'any', disabled: false },
);

const emit = defineEmits<{ (e: 'save', value: string | number | null): void }>();

const editing = ref(false);
const draft = ref<string>('');
const input = ref<HTMLInputElement | null>(null);

watch(
    () => props.modelValue,
    () => {
        if (!editing.value) draft.value = props.modelValue == null ? '' : String(props.modelValue);
    },
    { immediate: true },
);

const start = async () => {
    if (props.disabled) return;
    draft.value = props.modelValue == null ? '' : String(props.modelValue);
    editing.value = true;
    await nextTick();
    input.value?.focus();
    input.value?.select();
};

const cancel = () => {
    editing.value = false;
};

const save = () => {
    if (!editing.value) return;
    editing.value = false;
    const raw = draft.value.trim();
    let value: string | number | null = raw === '' ? null : raw;
    if (props.type === 'number' && value !== null) value = Number(value);
    const current = props.modelValue == null ? null : props.modelValue;
    if (String(value ?? '') === String(current ?? '')) return;
    emit('save', value);
};
</script>

<template>
    <input
        v-if="editing"
        ref="input"
        v-model="draft"
        :type="type"
        :min="min"
        :step="type === 'number' ? step : undefined"
        :class="['h-8 rounded-md border border-input bg-background px-2 text-sm ring-2 ring-ring', inputClass]"
        @keydown.enter.prevent="save"
        @keydown.esc.prevent="cancel"
        @blur="save"
    />
    <button
        v-else
        type="button"
        :class="[
            'min-h-8 w-full rounded-md px-2 py-1 text-left hover:bg-accent focus:bg-accent focus:outline-none',
            disabled ? 'cursor-default' : 'cursor-text',
            modelValue == null || modelValue === '' ? 'text-muted-foreground' : '',
        ]"
        :title="disabled ? undefined : 'Click to edit'"
        @click="start"
    >
        {{ display ?? (modelValue == null || modelValue === '' ? placeholder : modelValue) }}
    </button>
</template>
