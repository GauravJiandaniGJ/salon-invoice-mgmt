<?php

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StaffMember;
use App\Models\User;
use Database\Seeders\FreshSalonSeeder;
use Illuminate\Support\Facades\Hash;

test('the fresh seed leaves only the two logins and the CSV catalog', function () {
    User::factory()->owner()->create(['email' => 'owner@wowsalon.local']);
    Invoice::factory()->has(InvoiceItem::factory()->count(2), 'items')->create();
    Expense::factory()->create();

    $this->seed(FreshSalonSeeder::class);

    expect(User::pluck('email')->sort()->values()->all())
        ->toBe([FreshSalonSeeder::OWNER_EMAIL, FreshSalonSeeder::STAFF_EMAIL])
        ->and(Invoice::count())->toBe(0)
        ->and(InvoiceItem::count())->toBe(0)
        ->and(Customer::withTrashed()->count())->toBe(0)
        ->and(Expense::withTrashed()->count())->toBe(0)
        ->and(StaffMember::count())->toBe(0);

    $owner = User::where('email', FreshSalonSeeder::OWNER_EMAIL)->firstOrFail();
    $staff = User::where('email', FreshSalonSeeder::STAFF_EMAIL)->firstOrFail();

    expect($owner->role)->toBe(User::ROLE_OWNER)
        ->and($staff->role)->toBe(User::ROLE_STAFF)
        ->and($owner->is_active)->toBeTrue()
        ->and($staff->is_active)->toBeTrue()
        ->and(Hash::check(FreshSalonSeeder::PASSWORD, $owner->password))->toBeTrue()
        ->and(Hash::check(FreshSalonSeeder::PASSWORD, $staff->password))->toBeTrue();
});

test('the seeded catalog matches the CSV row for row', function () {
    $this->seed(FreshSalonSeeder::class);

    $handle = fopen(base_path(FreshSalonSeeder::CATALOG), 'r');
    fgetcsv($handle);

    $expected = [];

    while (($row = fgetcsv($handle)) !== false) {
        $expected[] = ['category' => $row[0], 'group' => $row[2], 'name' => $row[3], 'price' => (float) $row[4]];
    }

    fclose($handle);

    expect(Service::count())->toBe(count($expected))
        ->and(ServiceCategory::count())->toBe(count(array_unique(array_column($expected, 'category'))));

    // Names repeat across categories ("Underarms" is waxed and bleached), so a
    // row is only identified by category + group + name.
    foreach ($expected as $row) {
        $service = Service::whereRelation('category', 'name', $row['category'])
            ->where('group_name', $row['group'])
            ->where('name', $row['name'])
            ->firstOrFail();

        expect((float) $service->price)->toBe($row['price'])
            ->and($service->is_active)->toBeTrue();
    }

    // Categories carry the audience from the CSV `gender` column.
    expect(ServiceCategory::where('name', 'Facials')->value('audience'))->toBe('all')
        ->and(ServiceCategory::where('name', 'Cut & Style – Men')->value('audience'))->toBe('men')
        ->and(ServiceCategory::where('name', 'Cut & Style – Women')->value('audience'))->toBe('women');

    // The only ranged price on the menu keeps its upper bound.
    expect((float) Service::where('name', 'Advanced Nail Art per Finger (Stones/Accessories)')->value('price_max'))
        ->toBe(500.0);
});

test('running the fresh seed twice is safe', function () {
    $this->seed(FreshSalonSeeder::class);
    $count = Service::count();

    $this->seed(FreshSalonSeeder::class);

    expect(User::count())->toBe(2)
        ->and(Service::count())->toBe($count)
        ->and(ServiceCategory::count())->toBe(14);
});
