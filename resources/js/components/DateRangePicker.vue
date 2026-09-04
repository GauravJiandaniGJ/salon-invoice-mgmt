<script setup lang="ts">
import DateInput from '@/components/DateInput.vue';
import { Button } from '@/components/ui/button';
import { presetRange, todayIso, type RangePreset } from '@/lib/dates';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ from: string; to: string }>();
const emit = defineEmits<{ (e: 'change', range: { from: string; to: string }): void }>();

const from = ref(props.from);
const to = ref(props.to);
watch(
    () => [props.from, props.to],
    ([f, t]) => {
        from.value = f;
        to.value = t;
    },
);

const presets: { key: RangePreset; label: string }[] = [
    { key: 'today', label: 'Today' },
    { key: 'yesterday', label: 'Yesterday' },
    { key: 'week', label: 'This week' },
    { key: 'month', label: 'This month' },
    { key: 'last_month', label: 'Last month' },
];

const activePreset = computed(
    () =>
        presets.find((p) => {
            const r = presetRange(p.key);
            return r.from === props.from && r.to === props.to;
        })?.key ?? null,
);

const pick = (key: RangePreset) => emit('change', presetRange(key));
const applyCustom = () => {
    if (!from.value || !to.value) return;
    const [f, t] = from.value <= to.value ? [from.value, to.value] : [to.value, from.value];
    emit('change', { from: f, to: t });
};
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <div class="flex flex-wrap gap-1 rounded-lg border bg-card p-1">
            <button
                v-for="p in presets"
                :key="p.key"
                type="button"
                class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
                :class="activePreset === p.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                @click="pick(p.key)"
            >
                {{ p.label }}
            </button>
        </div>
        <div class="flex items-center gap-1.5">
            <DateInput v-model="from" :max="to || todayIso()" class="h-9 w-[150px]" aria-label="From" />
            <span class="text-xs text-muted-foreground">to</span>
            <DateInput v-model="to" :min="from" :max="todayIso()" class="h-9 w-[150px]" aria-label="To" />
            <Button variant="outline" size="sm" class="h-9" :disabled="from === props.from && to === props.to" @click="applyCustom">Apply</Button>
        </div>
    </div>
</template>
