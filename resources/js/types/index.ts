/*
 * Shared TypeScript contract (owned by the orchestrator — see docs/CONTRACT.md).
 * Backend controllers produce exactly these shapes as Inertia props.
 */
import type { LucideIcon } from 'lucide-vue-next';

export type Role = 'owner' | 'staff';
export type PaymentMode = 'cash' | 'upi' | 'card' | 'other';
export type PaymentStatus = 'paid' | 'unpaid';
export type DiscountType = 'flat' | 'percent';
export type InvoiceStatus = 'issued' | 'void';
export type Audience = 'women' | 'men' | 'all';
export type Gender = 'female' | 'male' | 'other';

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
    salon: { name: string; logo_url: string | null; brand_color: string };
    powered_by: { name: string; url: string; label: string };
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

/** Laravel LengthAwarePaginator (->paginate(n)->withQueryString()) as serialised by Inertia. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
    prev_page_url: string | null;
    next_page_url: string | null;
}

export type ByMode = Record<PaymentMode, number>;

// ---------- Catalog ----------
export interface CatalogService {
    id: number;
    group_name: string | null;
    name: string;
    display_name: string;
    description: string | null;
    price: number;
    price_max: number | null;
    duration_minutes: number | null;
}

export interface CatalogCategory {
    id: number;
    name: string;
    audience: Audience;
    services: CatalogService[];
}

export interface StaffMemberOption {
    id: number;
    name: string;
}

// ---------- Customers ----------
export interface CustomerLookup {
    id: number;
    name: string;
    phone: string; // normalised, e.g. 919876543210
    phone_display: string; // +91 98765 43210
    gender: Gender | null;
    notes: string | null;
    last_visit_at: string | null; // ISO datetime
    total_spent: number;
    visits: number;
    last_invoice: { id: number; invoice_number: string; total: number; invoice_date: string } | null;
}

export interface CustomerLookupResponse {
    found: boolean;
    customer: CustomerLookup | null;
    normalised_phone: string | null;
    error: string | null; // set when the phone is invalid
    matches?: CustomerLookup[]; // only for ?q= suggestion searches
}

export interface CustomerRow {
    id: number;
    name: string;
    phone: string;
    phone_display: string;
    gender: Gender | null;
    total_spent: number;
    visits: number;
    last_visit_at: string | null;
}

export interface CustomerDetail extends CustomerRow {
    notes: string | null;
    created_at: string;
}

// ---------- Billing ----------
export interface BillLineInput {
    service_id: number | null;
    description: string;
    unit_price: number;
    quantity: number;
}

/** POST /invoices payload */
export interface BillPayload {
    customer: { phone: string; name: string; gender: Gender | null };
    staff_member_id: number | null;
    invoice_date: string | null; // YYYY-MM-DD, owner only; null = today
    items: BillLineInput[];
    discount_type: DiscountType | null;
    discount_value: number;
    payment_mode: PaymentMode;
    payment_status: PaymentStatus;
    notes: string;
}

/** Prefill for New Bill (duplicate / customer shortcut) */
export interface BillPrefill {
    customer: { phone: string; name: string; gender: Gender | null } | null;
    staff_member_id: number | null;
    items: BillLineInput[];
    discount_type: DiscountType | null;
    discount_value: number;
    payment_mode: PaymentMode;
    notes: string;
}

/** bills/New extra prop when editing an issued invoice (PUT /invoices/{id}) */
export interface EditingInvoice {
    id: number;
    invoice_number: string;
    whatsapp_sent_at: string | null;
}

/** POST /invoices/{id}/send (json) */
export interface SendResponse {
    sent: boolean; // true when delivered server-side (cloud driver)
    whatsapp_sent_at: string | null;
    fallback_url: string | null; // open this when sent === false
    error: string | null;
}

export interface Totals {
    subtotal: number;
    discount_amount: number;
    tax_amount: number;
    round_off: number;
    total: number;
}

export interface InvoiceItemRow {
    id: number;
    service_id: number | null;
    description: string;
    unit_price: number;
    quantity: number;
    line_total: number;
}

export interface InvoiceRow {
    id: number;
    invoice_number: string;
    invoice_date: string; // YYYY-MM-DD
    customer: { id: number; name: string; phone_display: string };
    items_summary: string; // "Haircut – Men, Shave +2"
    total: number;
    payment_mode: PaymentMode;
    payment_status: PaymentStatus;
    status: InvoiceStatus;
    whatsapp_sent_at: string | null;
}

export interface InvoiceDetail {
    id: number;
    invoice_number: string;
    public_code: string;
    invoice_date: string;
    customer: { id: number; name: string; phone: string; phone_display: string; gender: Gender | null };
    staff_member: StaffMemberOption | null;
    billed_by: { id: number; name: string };
    items: InvoiceItemRow[];
    subtotal: number;
    discount_type: DiscountType | null;
    discount_value: number;
    discount_amount: number;
    tax_rate: number;
    tax_amount: number;
    round_off: number;
    total: number;
    payment_mode: PaymentMode;
    payment_status: PaymentStatus;
    notes: string | null;
    status: InvoiceStatus;
    void_reason: string | null;
    voided_at: string | null;
    voided_by: { id: number; name: string } | null;
    whatsapp_sent_at: string | null;
    created_at: string;
}

export interface InvoiceFilters {
    from: string; // YYYY-MM-DD
    to: string;
    status: '' | InvoiceStatus;
    payment_mode: '' | PaymentMode;
    sent: '' | 'sent' | 'unsent';
    q: string;
}

// ---------- Expenses ----------
export interface ExpenseRow {
    id: number;
    expense_date: string;
    category: string;
    description: string;
    amount: number;
    payment_mode: PaymentMode;
    user: { id: number; name: string };
    can_edit: boolean;
}

export interface ExpensePayload {
    expense_date: string;
    category: string;
    description: string;
    amount: number;
    payment_mode: PaymentMode;
}

// ---------- Reports ----------
export interface ReportInvoiceLine {
    id: number;
    invoice_number: string;
    customer_name: string;
    total: number;
    payment_mode: PaymentMode;
    staff_member: string | null;
    void_reason?: string | null;
}

export interface ReportExpenseLine {
    id: number;
    category: string;
    description: string;
    amount: number;
    payment_mode: PaymentMode;
}

export interface DailyReport {
    date: string; // YYYY-MM-DD
    date_label: string; // "Tue, 2 Sep 2026"
    invoices_count: number;
    customers_served: number;
    earnings: { total: number; by_mode: ByMode };
    expenses: { total: number; by_mode: ByMode };
    net: number;
    cash_in_hand: number;
    invoices: ReportInvoiceLine[];
    voided: ReportInvoiceLine[];
    expense_lines: ReportExpenseLine[];
}

export interface MonthlyReport {
    month: string; // YYYY-MM
    month_label: string; // "September 2026"
    days: { date: string; invoices_count: number; earnings: number; expenses: number; net: number }[];
    totals: { invoices_count: number; earnings: number; expenses: number; net: number };
    earnings_by_mode: ByMode;
    expenses_by_mode: ByMode;
    top_services: { description: string; count: number; revenue: number }[];
    by_staff: { staff_member: string; invoices_count: number; revenue: number }[];
}

export interface ServicesReport {
    from: string;
    to: string;
    rows: { service_id: number | null; description: string; count: number; quantity: number; revenue: number }[];
    totals: { count: number; revenue: number };
}

export interface DashboardData {
    today: { invoices_count: number; earnings: number; expenses: number; net: number; by_mode: ByMode };
    month: { invoices_count: number; earnings: number; expenses: number; net: number } | null; // owner only
    recent_invoices: InvoiceRow[];
}

// ---------- Settings ----------
export interface SalonSettings {
    salon_name: string;
    salon_tagline: string;
    salon_address: string;
    salon_phone: string;
    salon_whatsapp_number: string;
    invoice_prefix: string;
    tax_rate: number;
    whatsapp_template: string;
    footer_text: string;
    app_url: string;
    logo_url: string | null;
    brand_color: string; // hex, e.g. #0F766E
    whatsapp_driver: 'wame' | 'cloud';
    whatsapp_cloud_phone_id: string;
    whatsapp_cloud_template: string;
    whatsapp_cloud_token_set: boolean; // token itself is never sent to the client
}

export interface SettingsUserRow {
    id: number;
    name: string;
    email: string;
    role: Role;
    is_active: boolean;
}

export interface SettingsStaffRow {
    id: number;
    name: string;
    is_active: boolean;
}
