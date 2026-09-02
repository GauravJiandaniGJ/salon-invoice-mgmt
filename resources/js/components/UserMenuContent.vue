<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import type { User } from '@/types';
import { Link } from '@inertiajs/vue3';
import { LogOut, UserCog } from 'lucide-vue-next';

interface Props {
    user: User;
}

defineProps<Props>();

const roleLabel: Record<string, string> = { owner: 'Owner', staff: 'Receptionist' };
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
            <span
                class="ml-auto rounded-full border border-primary/40 bg-accent px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-accent-foreground"
            >
                {{ roleLabel[user.role] ?? user.role }}
            </span>
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="route('profile.edit')" as="button">
                <UserCog class="mr-2 h-4 w-4" />
                My account
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link class="block w-full" method="post" :href="route('logout')" as="button">
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
