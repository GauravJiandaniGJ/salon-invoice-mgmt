<?php

use App\Models\Invoice;
use App\Models\Setting;
use App\Models\StaffMember;
use App\Services\WhatsApp\MessageTemplate;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => $this->seed(SettingsSeeder::class));

test('settings page is owner only', function () {
    $this->actingAs(staff())->get('/settings')->assertForbidden();
    $this->actingAs(staff())->patch('/settings', ['salon_name' => 'X'])->assertForbidden();
});

test('owner sees settings props', function () {
    StaffMember::factory()->create(['name' => 'Asha']);

    $this->actingAs(owner())
        ->get('/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Index', false)
            ->where('settings.salon_name', 'Wow Salon')
            ->where('settings.invoice_prefix', 'WS')
            ->where('settings.tax_rate', 0)
            ->where('settings.logo_url', null)
            ->where('next_invoice_number', 'WS-0001')
            ->has('users', 1)
            ->where('users.0.role', 'owner')
            ->has('staff_members', 1)
            ->where('staff_members.0.name', 'Asha')
            ->has('whatsapp_placeholders')
        );
});

test('next invoice number follows the highest existing number', function () {
    Invoice::factory()->create(['invoice_number' => 'WS-0042']);
    Invoice::factory()->create(['invoice_number' => 'WS-0007']);

    $this->actingAs(owner())
        ->get('/settings')
        ->assertInertia(fn ($page) => $page->where('next_invoice_number', 'WS-0043'));
});

test('owner can update settings and Setting::get reflects it', function () {
    $this->actingAs(owner())
        ->from('/settings')
        ->patch('/settings', [
            'salon_name' => 'Meraki Salon',
            'salon_tagline' => 'Look good',
            'salon_address' => 'MG Road',
            'salon_phone' => '079 1234',
            'salon_whatsapp_number' => '98765 43210',
            'invoice_prefix' => 'mk',
            'tax_rate' => 5,
            'whatsapp_template' => 'Hi {customer_name}',
            'footer_text' => 'Thanks',
            'app_url' => 'https://wowsalon.to/',
        ])
        ->assertRedirect('/settings')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    Setting::flushCache();
    expect(Setting::get('salon_name'))->toBe('Meraki Salon')
        ->and(Setting::get('invoice_prefix'))->toBe('MK')
        ->and(Setting::get('salon_whatsapp_number'))->toBe('919876543210')
        ->and((float) Setting::get('tax_rate'))->toBe(5.0)
        ->and(Setting::get('app_url'))->toBe('https://wowsalon.to');
});

test('settings validation rejects bad prefix, phone and url', function () {
    $this->actingAs(owner())
        ->from('/settings')
        ->patch('/settings', [
            'salon_name' => '',
            'invoice_prefix' => 'w-1',
            'salon_whatsapp_number' => '12345',
            'app_url' => 'not a url',
            'tax_rate' => 120,
        ])
        ->assertSessionHasErrors(['salon_name', 'invoice_prefix', 'salon_whatsapp_number', 'app_url', 'tax_rate']);
});

test('logo upload stores the file and remove deletes it', function () {
    Storage::fake('public');

    $this->actingAs(owner())
        ->post('/settings/logo', ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)])
        ->assertRedirect()
        ->assertSessionHas('success');

    Setting::flushCache();
    $path = Setting::get('logo_path');
    expect($path)->toStartWith('logos/');
    Storage::disk('public')->assertExists($path);

    $this->actingAs(owner())->get('/settings')
        ->assertInertia(fn ($page) => $page->where('settings.logo_url', fn ($url) => str_contains($url, $path)));

    $this->actingAs(owner())->delete('/settings/logo')->assertRedirect();
    Storage::disk('public')->assertMissing($path);
    Setting::flushCache();
    expect(Setting::get('logo_path'))->toBe('');
});

test('logo upload rejects non-images', function () {
    Storage::fake('public');

    $this->actingAs(owner())
        ->post('/settings/logo', ['logo' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('logo');
});

test('whatsapp preview renders the template through MessageTemplate', function () {
    $this->mock(MessageTemplate::class)
        ->shouldReceive('render')
        ->once()
        ->withArgs(fn (string $template, $invoice) => $template === 'Hello {customer_name}' && $invoice instanceof Invoice)
        ->andReturn('Hello Priya');

    $this->actingAs(owner())
        ->getJson('/settings/whatsapp-preview?template=Hello+{customer_name}')
        ->assertOk()
        ->assertJson(['message' => 'Hello Priya']);
});
