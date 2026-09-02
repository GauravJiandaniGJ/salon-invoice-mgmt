<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Role, type SalonSettings, type SettingsStaffRow, type SettingsUserRow, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ImageOff, KeyRound, Plus, Upload, Users } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    settings: SalonSettings;
    next_invoice_number: string;
    users: SettingsUserRow[];
    staff_members: SettingsStaffRow[];
    whatsapp_placeholders: string[];
}>();

const page = usePage<SharedData>();
const me = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Settings', href: '/settings' }];

const sections = [
    { id: 'salon', title: 'Salon' },
    { id: 'invoice', title: 'Invoice' },
    { id: 'whatsapp', title: 'WhatsApp' },
    { id: 'users', title: 'Users' },
    { id: 'staff', title: 'Staff members' },
];

// ---------- salon + invoice + whatsapp (one PATCH) ----------
const form = useForm({
    salon_name: props.settings.salon_name ?? '',
    salon_tagline: props.settings.salon_tagline ?? '',
    salon_address: props.settings.salon_address ?? '',
    salon_phone: props.settings.salon_phone ?? '',
    salon_whatsapp_number: props.settings.salon_whatsapp_number ?? '',
    invoice_prefix: props.settings.invoice_prefix ?? 'WS',
    tax_rate: props.settings.tax_rate ?? 0,
    whatsapp_template: props.settings.whatsapp_template ?? '',
    footer_text: props.settings.footer_text ?? '',
    app_url: props.settings.app_url ?? '',
});

const save = () => form.patch('/settings', { preserveScroll: true });

// ---------- logo ----------
const logoInput = ref<HTMLInputElement | null>(null);
const logoUploading = ref(false);
const logoError = ref('');
const uploadLogo = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    logoError.value = '';
    logoUploading.value = true;
    router.post(
        '/settings/logo',
        { logo: file },
        {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => (logoError.value = errors.logo ?? 'Upload failed.'),
            onFinish: () => {
                logoUploading.value = false;
                if (logoInput.value) logoInput.value.value = '';
            },
        },
    );
};
const removeLogo = () => {
    if (!confirm('Remove the logo?')) return;
    router.delete('/settings/logo', { preserveScroll: true });
};

// ---------- whatsapp live preview ----------
const preview = ref('');
const previewLoading = ref(false);
let previewTimer: ReturnType<typeof setTimeout> | null = null;
let previewAbort: AbortController | null = null;

const fetchPreview = async (template: string) => {
    previewAbort?.abort();
    previewAbort = new AbortController();
    previewLoading.value = true;
    try {
        const res = await fetch(`/settings/whatsapp-preview?template=${encodeURIComponent(template)}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: previewAbort.signal,
        });
        if (res.ok) preview.value = (await res.json()).message ?? '';
    } catch (err) {
        if ((err as Error).name !== 'AbortError') preview.value = '';
    } finally {
        previewLoading.value = false;
    }
};

watch(
    () => form.whatsapp_template,
    (t) => {
        if (previewTimer) clearTimeout(previewTimer);
        previewTimer = setTimeout(() => fetchPreview(t), 400);
    },
    { immediate: true },
);
onBeforeUnmount(() => {
    if (previewTimer) clearTimeout(previewTimer);
    previewAbort?.abort();
});

const insertPlaceholder = (ph: string) => {
    form.whatsapp_template = `${form.whatsapp_template}${form.whatsapp_template.endsWith(' ') || form.whatsapp_template === '' ? '' : ' '}${ph}`;
};

// ---------- users ----------
const showAddUser = ref(false);
const userForm = useForm({ name: '', email: '', role: 'staff' as Role, password: '' });
const addUser = () => {
    userForm.post('/settings/users', {
        preserveScroll: true,
        onSuccess: () => {
            userForm.reset();
            showAddUser.value = false;
        },
    });
};

const updateUser = (user: SettingsUserRow, data: Partial<{ role: Role; is_active: boolean; password: string }>) => {
    router.patch(`/settings/users/${user.id}`, data, { preserveScroll: true });
};

const resetPassword = (user: SettingsUserRow) => {
    const password = prompt(`New password for ${user.name} (min 8 characters):`);
    if (!password) return;
    if (password.length < 8) {
        alert('Password must be at least 8 characters.');
        return;
    }
    updateUser(user, { password });
};

const toggleUserActive = (user: SettingsUserRow) => {
    if (user.is_active && !confirm(`Deactivate ${user.name}? They will no longer be able to log in.`)) return;
    updateUser(user, { is_active: !user.is_active });
};

// ---------- staff members ----------
const staffForm = useForm({ name: '' });
const addStaff = () => {
    staffForm.post('/settings/staff-members', {
        preserveScroll: true,
        onSuccess: () => staffForm.reset(),
    });
};
const toggleStaff = (s: SettingsStaffRow) => {
    router.patch(`/settings/staff-members/${s.id}`, { is_active: !s.is_active }, { preserveScroll: true });
};
const renameStaff = (s: SettingsStaffRow) => {
    const name = prompt('Staff member name:', s.name);
    if (!name || name.trim() === s.name) return;
    router.patch(`/settings/staff-members/${s.id}`, { name: name.trim() }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4">
            <div>
                <h1 class="text-2xl font-semibold">Settings</h1>
                <p class="text-sm text-muted-foreground">Salon details, invoice options, WhatsApp message and the people who use this app.</p>
            </div>

            <nav class="flex gap-1 overflow-x-auto rounded-xl border bg-card p-1 shadow-sm text-sm">
                <a
                    v-for="s in sections"
                    :key="s.id"
                    :href="`#${s.id}`"
                    class="whitespace-nowrap rounded-md px-3 py-1.5 font-medium text-muted-foreground hover:text-foreground"
                >
                    {{ s.title }}
                </a>
            </nav>

            <form class="flex flex-col gap-5" @submit.prevent="save">
                <!-- ================= Salon ================= -->
                <section id="salon" class="scroll-mt-20 rounded-xl border bg-card shadow-sm p-4">
                    <h2 class="text-base font-semibold">Salon details</h2>
                    <p class="mb-4 text-sm text-muted-foreground">Shown on invoices, the public invoice page and the PDF.</p>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-1">
                            <Label for="salon_name">Salon name</Label>
                            <Input id="salon_name" v-model="form.salon_name" required />
                            <p v-if="form.errors.salon_name" class="text-xs text-destructive">{{ form.errors.salon_name }}</p>
                        </div>
                        <div class="grid gap-1">
                            <Label for="salon_tagline">Tagline</Label>
                            <Input id="salon_tagline" v-model="form.salon_tagline" placeholder="The Unisex Salon" />
                            <p v-if="form.errors.salon_tagline" class="text-xs text-destructive">{{ form.errors.salon_tagline }}</p>
                        </div>
                        <div class="grid gap-1 md:col-span-2">
                            <Label for="salon_address">Address</Label>
                            <textarea
                                id="salon_address"
                                v-model="form.salon_address"
                                rows="2"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            ></textarea>
                            <p v-if="form.errors.salon_address" class="text-xs text-destructive">{{ form.errors.salon_address }}</p>
                        </div>
                        <div class="grid gap-1">
                            <Label for="salon_phone">Display phone</Label>
                            <Input id="salon_phone" v-model="form.salon_phone" placeholder="+91 98765 43210" />
                            <p v-if="form.errors.salon_phone" class="text-xs text-destructive">{{ form.errors.salon_phone }}</p>
                        </div>
                        <div class="grid gap-1">
                            <Label for="salon_whatsapp_number">WhatsApp number</Label>
                            <Input id="salon_whatsapp_number" v-model="form.salon_whatsapp_number" placeholder="98765 43210" />
                            <p class="text-xs text-muted-foreground">The number WhatsApp Web is logged in with on the salon laptop.</p>
                            <p v-if="form.errors.salon_whatsapp_number" class="text-xs text-destructive">{{ form.errors.salon_whatsapp_number }}</p>
                        </div>
                        <div class="grid gap-1">
                            <Label for="footer_text">Invoice footer</Label>
                            <Input id="footer_text" v-model="form.footer_text" placeholder="Powered by TodoIT" />
                            <p v-if="form.errors.footer_text" class="text-xs text-destructive">{{ form.errors.footer_text }}</p>
                        </div>
                        <div class="grid gap-1">
                            <Label for="app_url">Public website URL</Label>
                            <Input id="app_url" v-model="form.app_url" type="url" placeholder="https://wowsalon.example.com" />
                            <p class="text-xs text-muted-foreground">
                                Used to build the invoice link in WhatsApp messages. Must be the real HTTPS domain.
                            </p>
                            <p v-if="form.errors.app_url" class="text-xs text-destructive">{{ form.errors.app_url }}</p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap items-center gap-4 border-t pt-4">
                        <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-md border bg-muted">
                            <img v-if="settings.logo_url" :src="settings.logo_url" alt="Salon logo" class="h-full w-full object-contain" />
                            <ImageOff v-else class="h-6 w-6 text-muted-foreground" />
                        </div>
                        <div class="grid gap-1">
                            <Label>Logo</Label>
                            <div class="flex flex-wrap gap-2">
                                <Button type="button" variant="outline" size="sm" :disabled="logoUploading" @click="logoInput?.click()">
                                    <Upload /> {{ logoUploading ? 'Uploading…' : settings.logo_url ? 'Replace' : 'Upload' }}
                                </Button>
                                <Button v-if="settings.logo_url" type="button" variant="ghost" size="sm" class="text-destructive" @click="removeLogo"
                                    >Remove</Button
                                >
                            </div>
                            <input ref="logoInput" type="file" accept="image/png,image/jpeg" class="hidden" @change="uploadLogo" />
                            <p class="text-xs text-muted-foreground">PNG or JPG, up to 2 MB.</p>
                            <p v-if="logoError" class="text-xs text-destructive">{{ logoError }}</p>
                        </div>
                    </div>
                </section>

                <!-- ================= Invoice ================= -->
                <section id="invoice" class="scroll-mt-20 rounded-xl border bg-card shadow-sm p-4">
                    <h2 class="text-base font-semibold">Invoice</h2>
                    <p class="mb-4 text-sm text-muted-foreground">Numbering and tax.</p>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="grid gap-1">
                            <Label for="invoice_prefix">Invoice prefix</Label>
                            <Input id="invoice_prefix" v-model="form.invoice_prefix" maxlength="6" class="uppercase" required />
                            <p v-if="form.errors.invoice_prefix" class="text-xs text-destructive">{{ form.errors.invoice_prefix }}</p>
                        </div>
                        <div class="grid gap-1">
                            <Label>Next invoice number</Label>
                            <Input :model-value="next_invoice_number" disabled />
                            <p class="text-xs text-muted-foreground">Sequential and gap-free. Void invoices instead of deleting them.</p>
                        </div>
                        <div class="grid gap-1">
                            <Label for="tax_rate">GST %</Label>
                            <Input id="tax_rate" v-model="form.tax_rate" type="number" min="0" max="100" step="0.01" />
                            <p class="text-xs text-muted-foreground">Leave at 0 to hide tax on bills.</p>
                            <p v-if="form.errors.tax_rate" class="text-xs text-destructive">{{ form.errors.tax_rate }}</p>
                        </div>
                    </div>
                </section>

                <!-- ================= WhatsApp ================= -->
                <section id="whatsapp" class="scroll-mt-20 rounded-xl border bg-card shadow-sm p-4">
                    <h2 class="text-base font-semibold">WhatsApp message</h2>
                    <p class="mb-4 text-sm text-muted-foreground">
                        This text is pre-filled when you press “Send on WhatsApp”. Keep it under ~700 characters.
                    </p>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="whatsapp_template">Template</Label>
                            <textarea
                                id="whatsapp_template"
                                v-model="form.whatsapp_template"
                                rows="9"
                                class="rounded-md border border-input bg-background px-3 py-2 font-mono text-sm"
                            ></textarea>
                            <p class="text-xs text-muted-foreground">{{ form.whatsapp_template.length }} characters</p>
                            <p v-if="form.errors.whatsapp_template" class="text-xs text-destructive">{{ form.errors.whatsapp_template }}</p>
                            <div class="flex flex-wrap gap-1">
                                <button
                                    v-for="ph in whatsapp_placeholders"
                                    :key="ph"
                                    type="button"
                                    class="rounded-full border bg-background px-2 py-0.5 font-mono text-xs hover:bg-accent"
                                    :title="`Insert ${ph}`"
                                    @click="insertPlaceholder(ph)"
                                >
                                    {{ ph }}
                                </button>
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label>Preview <span v-if="previewLoading" class="font-normal text-muted-foreground">(updating…)</span></Label>
                            <div
                                class="min-h-[220px] whitespace-pre-wrap rounded-lg border bg-[#e7f6e4] p-3 text-sm text-neutral-900 dark:bg-[#1f3a2a] dark:text-neutral-100"
                            >
                                {{ preview || 'Preview will appear here.' }}
                            </div>
                        </div>
                    </div>
                </section>

                <div class="sticky bottom-0 flex items-center justify-end gap-3 border-t bg-background/95 py-3 backdrop-blur">
                    <span v-if="form.recentlySuccessful" class="text-sm text-muted-foreground">Saved.</span>
                    <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save settings' }}</Button>
                </div>
            </form>

            <!-- ================= Users ================= -->
            <section id="users" class="scroll-mt-20 rounded-xl border bg-card shadow-sm">
                <header class="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                    <div>
                        <h2 class="text-base font-semibold">Users</h2>
                        <p class="text-sm text-muted-foreground">People who can log in. Owners manage everything; staff can bill and add expenses.</p>
                    </div>
                    <Button size="sm" @click="showAddUser = !showAddUser"><Plus /> Add user</Button>
                </header>

                <form v-if="showAddUser" class="grid gap-3 border-b bg-muted/30 p-4 md:grid-cols-5" @submit.prevent="addUser">
                    <div class="grid gap-1">
                        <Label for="u-name">Name</Label>
                        <Input id="u-name" v-model="userForm.name" required autofocus />
                        <p v-if="userForm.errors.name" class="text-xs text-destructive">{{ userForm.errors.name }}</p>
                    </div>
                    <div class="grid gap-1">
                        <Label for="u-email">Email</Label>
                        <Input id="u-email" v-model="userForm.email" type="email" required />
                        <p v-if="userForm.errors.email" class="text-xs text-destructive">{{ userForm.errors.email }}</p>
                    </div>
                    <div class="grid gap-1">
                        <Label for="u-role">Role</Label>
                        <select id="u-role" v-model="userForm.role" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="staff">Staff</option>
                            <option value="owner">Owner</option>
                        </select>
                    </div>
                    <div class="grid gap-1">
                        <Label for="u-pass">Password</Label>
                        <Input id="u-pass" v-model="userForm.password" type="text" minlength="8" required autocomplete="new-password" />
                        <p v-if="userForm.errors.password" class="text-xs text-destructive">{{ userForm.errors.password }}</p>
                    </div>
                    <div class="flex items-end gap-2">
                        <Button type="submit" :disabled="userForm.processing">Create</Button>
                        <Button type="button" variant="ghost" @click="showAddUser = false">Cancel</Button>
                    </div>
                </form>

                <EmptyState v-if="users.length === 0" title="No users" :icon="Users" class="m-4" />
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Name</th>
                                <th class="px-4 py-2 font-medium">Email</th>
                                <th class="px-4 py-2 font-medium">Role</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="u in users" :key="u.id" class="border-t" :class="{ 'text-muted-foreground': !u.is_active }">
                                <td class="px-4 py-2">
                                    {{ u.name }}
                                    <span v-if="u.id === me.id" class="ml-1 rounded bg-muted px-1.5 py-0.5 text-[10px] uppercase">you</span>
                                </td>
                                <td class="px-4 py-2">{{ u.email }}</td>
                                <td class="px-4 py-2">
                                    <select
                                        :value="u.role"
                                        :disabled="u.id === me.id"
                                        class="h-8 rounded-md border border-input bg-background px-2 text-xs"
                                        @change="(e) => updateUser(u, { role: (e.target as HTMLSelectElement).value as Role })"
                                    >
                                        <option value="staff">Staff</option>
                                        <option value="owner">Owner</option>
                                    </select>
                                </td>
                                <td class="px-4 py-2">
                                    <span :class="u.is_active ? 'text-green-700 dark:text-green-400' : ''">{{
                                        u.is_active ? 'Active' : 'Inactive'
                                    }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <Button variant="ghost" size="sm" title="Set a new password" @click="resetPassword(u)"
                                        ><KeyRound /> Reset password</Button
                                    >
                                    <Button variant="ghost" size="sm" :disabled="u.id === me.id" @click="toggleUserActive(u)">
                                        {{ u.is_active ? 'Deactivate' : 'Activate' }}
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ================= Staff members ================= -->
            <section id="staff" class="scroll-mt-20 rounded-xl border bg-card shadow-sm">
                <header class="border-b px-4 py-3">
                    <h2 class="text-base font-semibold">Staff members</h2>
                    <p class="text-sm text-muted-foreground">
                        Stylists and therapists you can pick on a bill to track earnings per person. They don't log in.
                    </p>
                </header>

                <form class="flex flex-wrap items-end gap-2 border-b p-4" @submit.prevent="addStaff">
                    <div class="grid flex-1 gap-1">
                        <Label for="s-name">Name</Label>
                        <Input id="s-name" v-model="staffForm.name" placeholder="e.g. Priya" required class="max-w-sm" />
                        <p v-if="staffForm.errors.name" class="text-xs text-destructive">{{ staffForm.errors.name }}</p>
                    </div>
                    <Button type="submit" :disabled="staffForm.processing"><Plus /> Add</Button>
                </form>

                <EmptyState
                    v-if="staff_members.length === 0"
                    title="No staff members yet"
                    description="Add your stylists to see earnings per person in the monthly report."
                    class="m-4"
                />
                <ul v-else class="divide-y">
                    <li
                        v-for="s in staff_members"
                        :key="s.id"
                        class="flex items-center justify-between gap-3 px-4 py-2 text-sm"
                        :class="{ 'text-muted-foreground': !s.is_active }"
                    >
                        <button type="button" class="text-left hover:underline" title="Rename" @click="renameStaff(s)">{{ s.name }}</button>
                        <div class="flex items-center gap-2">
                            <span class="text-xs" :class="s.is_active ? 'text-green-700 dark:text-green-400' : ''">{{
                                s.is_active ? 'Active' : 'Inactive'
                            }}</span>
                            <Button variant="ghost" size="sm" @click="toggleStaff(s)">{{ s.is_active ? 'Deactivate' : 'Activate' }}</Button>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
