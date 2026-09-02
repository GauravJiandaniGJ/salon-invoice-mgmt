import type { LucideIcon } from 'lucide-vue-next';

export type Role = 'owner' | 'staff';

export interface User {
    id: number;
    name: string;
    email: string;
    role: Role;
    is_active?: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    ownerOnly?: boolean;
}

export interface SharedData {
    name: string;
    salon: { name: string; logo_url: string | null };
    auth: Auth;
    flash: { success: string | null; error: string | null };
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export type BreadcrumbItemType = BreadcrumbItem;
