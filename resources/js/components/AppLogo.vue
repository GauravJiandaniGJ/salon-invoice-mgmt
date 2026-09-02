<script setup lang="ts">
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const page = usePage<SharedData>();
const isDark = ref(document.documentElement.classList.contains('dark'));
onMounted(() => {
    const obs = new MutationObserver(() => (isDark.value = document.documentElement.classList.contains('dark')));
    obs.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});
const logo = computed(() => (isDark.value ? page.props.salon.logo_url_dark : page.props.salon.logo_url) || '/brand/wow-logo-transparent.png');
</script>

<template>
    <div class="flex h-9 w-11 shrink-0 items-center justify-center overflow-hidden">
        <img :src="logo" alt="" class="h-9 w-auto object-contain" />
    </div>
    <div class="ml-1 grid flex-1 text-left">
        <span class="truncate font-display text-[15px] font-semibold tracking-wide text-sidebar-foreground">{{ page.props.salon.name }}</span>
        <span class="truncate text-[10px] uppercase tracking-[0.18em] text-muted-foreground">Billing</span>
    </div>
</template>
