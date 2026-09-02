<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BarChart3, FilePlus2, FileText, LayoutGrid, Scissors, Settings, Users, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();

const allNavItems: NavItem[] = [
    { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
    { title: 'New Bill', href: '/bills/new', icon: FilePlus2 },
    { title: 'Invoices', href: '/invoices', icon: FileText },
    { title: 'Customers', href: '/customers', icon: Users },
    { title: 'Expenses', href: '/expenses', icon: Wallet },
    { title: 'Reports', href: '/reports/daily', icon: BarChart3 },
    { title: 'Services', href: '/services', icon: Scissors, ownerOnly: true },
    { title: 'Settings', href: '/settings', icon: Settings, ownerOnly: true },
];

const mainNavItems = computed(() => {
    const isOwner = page.props.auth.user?.role === 'owner';
    return allNavItems.filter((item) => !item.ownerOnly || isOwner);
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
