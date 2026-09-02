<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
}>();

const page = usePage<SharedData>();
</script>

<template>
    <header
        class="flex h-14 shrink-0 items-center gap-2 border-b border-border bg-card px-4 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 md:rounded-t-xl"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumb>
                    <BreadcrumbList>
                        <template v-for="(item, index) in breadcrumbs" :key="index">
                            <BreadcrumbItem>
                                <template v-if="index === breadcrumbs.length - 1">
                                    <BreadcrumbPage>{{ item.title }}</BreadcrumbPage>
                                </template>
                                <template v-else>
                                    <BreadcrumbLink :href="item.href">
                                        {{ item.title }}
                                    </BreadcrumbLink>
                                </template>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>
            </template>
        </div>

        <a
            :href="page.props.powered_by.url"
            target="_blank"
            rel="noopener"
            :title="page.props.powered_by.label"
            class="ml-auto flex shrink-0 items-center gap-2 opacity-90 transition-opacity hover:opacity-100"
        >
            <span class="hidden text-[11px] uppercase tracking-wide text-muted-foreground sm:inline">Powered by</span>
            <img src="/brand/todoit-logo.png" alt="TodoIT" class="h-6 w-auto dark:hidden" /><img
                src="/brand/todoit-logo-light.png"
                alt="TodoIT"
                class="hidden h-6 w-auto dark:block"
            />
        </a>
    </header>
</template>
