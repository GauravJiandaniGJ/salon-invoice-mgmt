<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Models\StaffMember;
use App\Models\User;
use App\Services\WhatsApp\MessageTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public const WHATSAPP_PLACEHOLDERS = MessageTemplate::PLACEHOLDERS;

    public function index(): Response
    {
        return Inertia::render('settings/Index', [
            'settings' => $this->settingsProps(),
            'next_invoice_number' => $this->nextInvoiceNumber(),
            'users' => User::query()->orderBy('name')->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role,
                    'is_active' => $u->is_active,
                ])->values(),
            'staff_members' => StaffMember::query()->orderBy('name')->get()
                ->map(fn (StaffMember $s) => ['id' => $s->id, 'name' => $s->name, 'commission_percent' => (float) $s->commission_percent, 'is_active' => $s->is_active])
                ->values(),
            'whatsapp_placeholders' => self::WHATSAPP_PLACEHOLDERS,
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        foreach ($request->settings() as $key => $value) {
            Setting::set($key, $value);
        }

        return back()->with('success', 'Settings saved.');
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
        ]);

        $old = Setting::get('logo_path');
        $path = $request->file('logo')->store('logos', 'public');
        Setting::set('logo_path', $path);

        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        return back()->with('success', 'Logo updated.');
    }

    public function removeLogo(): RedirectResponse
    {
        if ($old = Setting::get('logo_path')) {
            Storage::disk('public')->delete($old);
        }
        Setting::set('logo_path', '');

        return back()->with('success', 'Logo removed.');
    }

    public function whatsappPreview(Request $request, MessageTemplate $template): JsonResponse
    {
        $text = (string) $request->query('template', Setting::get('whatsapp_template'));

        return response()->json([
            'message' => $template->render($text, $this->sampleInvoice()),
        ]);
    }

    /** @return array<string, mixed> matches TS SalonSettings */
    protected function settingsProps(): array
    {
        $logo = Setting::get('logo_path');

        return [
            'salon_name' => (string) Setting::get('salon_name', ''),
            'salon_tagline' => (string) Setting::get('salon_tagline', ''),
            'salon_address' => (string) Setting::get('salon_address', ''),
            'salon_phone' => (string) Setting::get('salon_phone', ''),
            'salon_whatsapp_number' => (string) Setting::get('salon_whatsapp_number', ''),
            'invoice_prefix' => (string) Setting::get('invoice_prefix', 'WS'),
            'tax_rate' => (float) Setting::get('tax_rate', 0),
            'whatsapp_template' => (string) Setting::get('whatsapp_template', ''),
            'footer_text' => (string) Setting::get('footer_text', ''),
            'app_url' => (string) Setting::get('app_url', ''),
            'logo_url' => $logo ? Storage::disk('public')->url($logo) : null,
            'brand_color' => (string) Setting::get('brand_color'),
            'whatsapp_driver' => (string) Setting::get('whatsapp_driver', 'wame') ?: 'wame',
            'whatsapp_cloud_phone_id' => (string) Setting::get('whatsapp_cloud_phone_id', ''),
            'whatsapp_cloud_template' => (string) Setting::get('whatsapp_cloud_template', 'invoice_ready'),
            'whatsapp_cloud_token_set' => trim((string) Setting::get('whatsapp_cloud_token', '')) !== '',
        ];
    }

    protected function nextInvoiceNumber(): string
    {
        $prefix = (string) Setting::get('invoice_prefix', 'WS');

        // numeric suffix after the last "-"; works for any prefix length
        $max = (int) DB::table('invoices')
            ->selectRaw("MAX(CAST(SUBSTR(invoice_number, INSTR(invoice_number, '-') + 1) AS INTEGER)) as m")
            ->value('m');

        return $prefix.'-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    protected function sampleInvoice(): Invoice
    {
        $latest = Invoice::query()->with(['customer', 'items'])->latest('id')->first();

        if ($latest) {
            return $latest;
        }

        $invoice = Invoice::factory()->make([
            'invoice_number' => Setting::get('invoice_prefix', 'WS').'-0001',
            'public_code' => 'SAMPLE0001',
            'subtotal' => 725,
            'total' => 725,
            'invoice_date' => now()->toDateString(),
        ]);
        $invoice->setRelation('customer', Customer::factory()->make(['name' => 'Priya Sharma', 'phone' => '919876543210']));
        $invoice->setRelation('items', collect([
            InvoiceItem::factory()->make(['description' => 'Female Haircut', 'unit_price' => 500, 'line_total' => 500]),
            InvoiceItem::factory()->make(['description' => 'Hair Wash – Upto Shoulder', 'unit_price' => 225, 'line_total' => 225]),
        ]));

        return $invoice;
    }
}
