<script setup lang="ts">
import { SidebarGroup, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    items: NavItem[];
}>();

const page = usePage<SharedData>();

const isActive = (href: string) => {
    const path = page.url.split('?')[0];
    if (href === '/dashboard') return path === href;
    // "/reports/daily" should light up for any /reports/* page
    const base = href.split('/').slice(0, 2).join('/');
    return path === href || path.startsWith(base + '/') || path === base;
};
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isActive(item.href)"
                    :tooltip="item.title"
                    class="h-10 rounded-lg text-[15px] hover:bg-sidebar-accent/70 hover:text-sidebar-accent-foreground data-[active=true]:bg-sidebar-primary data-[active=true]:font-semibold data-[active=true]:text-sidebar-primary-foreground"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
