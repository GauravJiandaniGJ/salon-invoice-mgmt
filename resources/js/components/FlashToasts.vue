<script setup lang="ts">
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, X, XCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Toast {
    id: number;
    type: 'success' | 'error';
    message: string;
}

const page = usePage<SharedData>();
const toasts = ref<Toast[]>([]);
let seq = 0;

const dismiss = (id: number) => {
    toasts.value = toasts.value.filter((t) => t.id !== id);
};

const push = (type: Toast['type'], message: string) => {
    const id = ++seq;
    toasts.value.push({ id, type, message });
    setTimeout(() => dismiss(id), 4000);
};

// Inertia hands us a fresh `flash` object on every visit, so watching the object
// (not its strings) fires again even when the same message repeats.
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) push('success', flash.success);
        if (flash?.error) push('error', flash.error);
    },
    { immediate: true },
);
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-3 z-50 flex flex-col items-center gap-2 px-3 print:hidden" aria-live="polite">
        <transition-group name="toast">
            <button
                v-for="toast in toasts"
                :key="toast.id"
                type="button"
                class="pointer-events-auto flex w-full max-w-md items-start gap-2 rounded-lg border px-3 py-2 text-left text-sm shadow-lg"
                :class="
                    toast.type === 'success'
                        ? 'border-green-200 bg-green-50 text-green-900 dark:border-green-900 dark:bg-green-950 dark:text-green-100'
                        : 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100'
                "
                @click="dismiss(toast.id)"
            >
                <CheckCircle2 v-if="toast.type === 'success'" class="mt-0.5 h-4 w-4 shrink-0" />
                <XCircle v-else class="mt-0.5 h-4 w-4 shrink-0" />
                <span class="flex-1">{{ toast.message }}</span>
                <X class="mt-0.5 h-4 w-4 shrink-0 opacity-60" />
            </button>
        </transition-group>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.2s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
