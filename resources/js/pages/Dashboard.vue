<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { FilePlus2 } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Dashboard</h1>
                <Button as-child size="lg">
                    <Link href="/bills/new"><FilePlus2 class="mr-2 h-5 w-5" /> New Bill</Link>
                </Button>
            </div>
            <p class="text-sm text-muted-foreground">Today's numbers will appear here once billing is set up.</p>
        </div>
    </AppLayout>
</template>
