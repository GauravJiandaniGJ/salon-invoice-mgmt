<script setup lang="ts">
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ active: 'daily' | 'monthly' | 'services' }>();

const page = usePage<SharedData>();
const isOwner = computed(() => page.props.auth.user?.role === 'owner');

const tabs = computed(() =>
    [
        { key: 'daily', title: 'Daily statement', href: '/reports/daily', ownerOnly: false },
        { key: 'monthly', title: 'Monthly', href: '/reports/monthly', ownerOnly: true },
        { key: 'services', title: 'Services', href: '/reports/services', ownerOnly: true },
    ].filter((t) => !t.ownerOnly || isOwner.value),
);
</script>

<template>
    <nav v-if="tabs.length > 1" class="flex gap-1 overflow-x-auto rounded-lg border bg-muted/40 p-1 text-sm print:hidden" aria-label="Reports">
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href"
            class="whitespace-nowrap rounded-md px-3 py-1.5 font-medium transition-colors"
            :class="props.active === tab.key ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground'"
        >
            {{ tab.title }}
        </Link>
    </nav>
</template>
