<?php

use App\Models\Service;
use App\Models\ServiceCategory;
use Database\Seeders\ServiceCatalogSeeder;

test('seeder populates the catalog', function () {
    $this->seed(ServiceCatalogSeeder::class);

    expect(ServiceCategory::count())->toBe(19)
        ->and(Service::count())->toBeGreaterThan(200);

    $men = Service::where('name', 'Haircut – Men')->first();
    expect($men)->not->toBeNull()
        ->and((float) $men->price)->toBe(225.0)
        ->and($men->category->audience)->toBe('men');

    $nailArt = Service::where('name', 'Nail Art Adv. per finger (stones / accessories)')->first();
    expect((float) $nailArt->price_max)->toBe(500.0);
});

test('seeder is idempotent and keeps owner edits', function () {
    $this->seed(ServiceCatalogSeeder::class);
    $categories = ServiceCategory::count();
    $services = Service::count();

    $men = Service::where('name', 'Haircut – Men')->first();
    $men->update(['price' => 250]);

    $this->seed(ServiceCatalogSeeder::class);

    expect(ServiceCategory::count())->toBe($categories)
        ->and(Service::count())->toBe($services)
        ->and((float) $men->fresh()->price)->toBe(250.0);
});

test('display name joins group and name', function () {
    $s = Service::factory()->make(['group_name' => 'Keratin', 'name' => 'Upto Waist']);
    expect($s->display_name)->toBe('Keratin – Upto Waist');

    $s = Service::factory()->make(['group_name' => null, 'name' => 'Shave']);
    expect($s->display_name)->toBe('Shave');
});
