<?php

use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\WowSalonSeeder;
use Illuminate\Support\Facades\Hash;

test('the handover seed leaves exactly the two salon logins, the catalog and the message', function () {
    User::factory()->owner()->create(['email' => 'owner@wowsalon.local']);
    User::factory()->staff()->create(['email' => 'someone-else@example.com']);

    $this->seed(WowSalonSeeder::class);

    expect(User::pluck('email')->sort()->values()->all())
        ->toBe([WowSalonSeeder::OWNER_EMAIL, WowSalonSeeder::STAFF_EMAIL]);

    $owner = User::where('email', WowSalonSeeder::OWNER_EMAIL)->firstOrFail();
    $staff = User::where('email', WowSalonSeeder::STAFF_EMAIL)->firstOrFail();

    expect($owner->role)->toBe(User::ROLE_OWNER)
        ->and($staff->role)->toBe(User::ROLE_STAFF)
        ->and(Hash::check(env('SALON_PASSWORD', 'WowSalon@2026'), $owner->password))->toBeTrue()
        ->and(Service::count())->toBeGreaterThan(200)
        ->and(Setting::get('whatsapp_template'))->toBe(WowSalonSeeder::TEMPLATE)
        ->and(Setting::get('whatsapp_template'))->toContain('😃');
});

test('running the handover seed twice is safe', function () {
    $this->seed(WowSalonSeeder::class);
    $this->seed(WowSalonSeeder::class);

    expect(User::count())->toBe(2)->and(Service::count())->toBeGreaterThan(200);
});
