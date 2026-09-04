<?php

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\Setting;
use App\Models\StaffMember;
use App\Models\User;
use App\Services\InvoiceNumber;
use Illuminate\Support\Facades\Hash;

function goLive(array $options = []): void
{
    test()->artisan('salon:go-live', array_merge([
        '--owner-email' => 'owner@wowsalon.in',
        '--owner-name' => 'Wow Owner',
        '--staff-email' => 'reception@wowsalon.in',
        '--staff-name' => 'Reception',
        '--password' => 'StrongPass123',
        '--force' => true,
    ], $options))->assertSuccessful();
}

test('go live clears demo data, seeds the catalog and creates both logins', function () {
    User::factory()->owner()->create(['email' => 'owner@wowsalon.local']);
    User::factory()->staff()->create(['email' => 'staff@wowsalon.local']);
    $invoice = Invoice::factory()->for(Customer::factory())->create();
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);
    Expense::factory()->create(['user_id' => User::first()->id]);

    goLive([
        '--salon-name' => 'Wow Salon',
        '--phone' => '9876543210',
        '--address' => 'MG Road, Surat',
        '--app-url' => 'https://wowsalon.todoitservices.com/',
        '--barbers' => 'Raj:10,Priya:12.5',
    ]);

    expect(Invoice::count())->toBe(0)
        ->and(InvoiceItem::count())->toBe(0)
        ->and(Customer::withTrashed()->count())->toBe(0)
        ->and(Expense::withTrashed()->count())->toBe(0)
        ->and(Service::count())->toBeGreaterThan(200)
        ->and(User::whereIn('email', ['owner@wowsalon.local', 'staff@wowsalon.local'])->count())->toBe(0);

    $owner = User::where('email', 'owner@wowsalon.in')->firstOrFail();
    $staff = User::where('email', 'reception@wowsalon.in')->firstOrFail();

    expect($owner->role)->toBe(User::ROLE_OWNER)
        ->and($staff->role)->toBe(User::ROLE_STAFF)
        ->and(Hash::check('StrongPass123', $owner->password))->toBeTrue()
        ->and(Hash::check('StrongPass123', $staff->password))->toBeTrue()
        ->and(Setting::get('salon_name'))->toBe('Wow Salon')
        ->and(Setting::get('salon_phone'))->toBe('+91 98765 43210')
        ->and(Setting::get('app_url'))->toBe('https://wowsalon.todoitservices.com')
        ->and(StaffMember::where('name', 'Priya')->first()->commission_percent)->toEqual(12.5);
});

test('invoice numbering restarts from one after going live', function () {
    Invoice::factory()->for(Customer::factory())->create(['invoice_number' => 'WS-0087']);

    goLive();

    expect(InvoiceNumber::next('WS'))->toBe('WS-0001');
});

test('keep-data leaves existing billing untouched', function () {
    $invoice = Invoice::factory()->for(Customer::factory())->create();

    goLive(['--keep-data' => true]);

    expect(Invoice::find($invoice->id))->not->toBeNull()
        ->and(User::where('email', 'owner@wowsalon.in')->exists())->toBeTrue();
});

test('a bad email or short password stops the command', function () {
    $this->artisan('salon:go-live', [
        '--owner-email' => 'not-an-email', '--staff-email' => 'reception@wowsalon.in',
        '--owner-name' => 'A', '--staff-name' => 'B', '--password' => 'StrongPass123', '--force' => true,
    ])->assertFailed();

    $this->artisan('salon:go-live', [
        '--owner-email' => 'owner@wowsalon.in', '--staff-email' => 'reception@wowsalon.in',
        '--owner-name' => 'A', '--staff-name' => 'B', '--password' => 'short', '--force' => true,
    ])->assertFailed();

    expect(User::where('email', 'owner@wowsalon.in')->exists())->toBeFalse();
});

test('running it twice is safe', function () {
    goLive();
    goLive(['--password' => 'SecondPass456']);

    expect(User::where('email', 'owner@wowsalon.in')->count())->toBe(1)
        ->and(Hash::check('SecondPass456', User::where('email', 'owner@wowsalon.in')->first()->password))->toBeTrue()
        ->and(Service::count())->toBeGreaterThan(200);
});
