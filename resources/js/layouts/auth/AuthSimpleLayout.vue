<script setup lang="ts">
import { useBrand } from '@/composables/useBrand';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage<SharedData>();
const isDark = ref(document.documentElement.classList.contains('dark'));
onMounted(() => {
    const obs = new MutationObserver(() => (isDark.value = document.documentElement.classList.contains('dark')));
    obs.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});
const logo = computed(() => (isDark.value ? page.props.salon.logo_url_dark : page.props.salon.logo_url) || '/brand/wow-logo-transparent.png');
useBrand();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center bg-background p-4 md:p-8">
        <div class="w-full max-w-sm overflow-hidden rounded-2xl border border-border bg-card shadow-lg">
            <div class="flex flex-col items-center gap-3 bg-transparent px-6 pb-6 pt-7 text-center">
                <img :src="logo" alt="" class="h-24 w-auto max-w-[220px] rounded-md object-contain" />
                <div>
                    <p class="font-display text-lg font-semibold tracking-wide text-white">{{ page.props.salon.name }}</p>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-primary">Billing &amp; Invoices</p>
                </div>
            </div>
            <div class="flex flex-col gap-6 p-6">
                <div class="space-y-1 text-center">
                    <h1 class="text-xl font-semibold">{{ title }}</h1>
                    <p class="text-sm text-muted-foreground">{{ description }}</p>
                </div>
                <slot />
            </div>
        </div>
        <a
            :href="page.props.powered_by.url"
            target="_blank"
            rel="noopener"
            class="mt-6 flex items-center justify-center gap-2 text-xs text-muted-foreground hover:text-foreground"
        >
            <span>Powered by</span>
            <img src="/brand/todoit-logo.png" alt="TodoIT" class="h-6 w-auto dark:hidden" /><img
                src="/brand/todoit-logo-light.png"
                alt="TodoIT"
                class="hidden h-6 w-auto dark:block"
            />
        </a>
    </div>
</template>
