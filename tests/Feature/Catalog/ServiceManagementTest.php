<?php

use App\Models\Service;
use App\Models\ServiceCategory;

test('staff cannot access services management', function () {
    $service = Service::factory()->create();

    $this->actingAs(staff())->get('/services')->assertForbidden();
    $this->actingAs(staff())->patch("/services/{$service->id}", ['price' => 1])->assertForbidden();
    $this->actingAs(staff())->post('/service-categories', ['name' => 'X', 'audience' => 'all'])->assertForbidden();
});

test('owner sees the services page with categories and services', function () {
    $category = ServiceCategory::factory()->create(['name' => 'Threading']);
    Service::factory()->create(['service_category_id' => $category->id, 'name' => 'Eyebrows', 'price' => 60]);

    $this->actingAs(owner())
        ->get('/services')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('services/Index')
            ->has('categories', 1)
            ->where('categories.0.name', 'Threading')
            ->where('categories.0.services.0.name', 'Eyebrows')
            ->where('categories.0.services.0.price', 60)
        );
});

test('owner can update a price inline', function () {
    $service = Service::factory()->create(['price' => 225]);

    $this->actingAs(owner())
        ->from('/services')
        ->patch("/services/{$service->id}", ['price' => 250])
        ->assertRedirect('/services')
        ->assertSessionHasNoErrors();

    expect((float) $service->fresh()->price)->toBe(250.0);
});

test('price must be a non-negative number', function () {
    $service = Service::factory()->create(['price' => 225]);

    $this->actingAs(owner())
        ->from('/services')
        ->patch("/services/{$service->id}", ['price' => -5])
        ->assertSessionHasErrors('price');

    expect((float) $service->fresh()->price)->toBe(225.0);
});

test('owner can add a category and a service', function () {
    $owner = owner();

    $this->actingAs($owner)->post('/service-categories', ['name' => 'Hair Spa', 'audience' => 'women'])->assertRedirect();
    $category = ServiceCategory::where('name', 'Hair Spa')->firstOrFail();
    expect($category->audience)->toBe('women')->and($category->is_active)->toBeTrue();

    $this->actingAs($owner)->post('/services', [
        'service_category_id' => $category->id,
        'group_name' => '',
        'name' => 'Spa Deluxe',
        'price' => 1200,
        'price_max' => '',
        'duration_minutes' => 45,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $service = Service::where('name', 'Spa Deluxe')->firstOrFail();
    expect($service->group_name)->toBeNull()
        ->and($service->price_max)->toBeNull()
        ->and($service->duration_minutes)->toBe(45)
        ->and($service->sort_order)->toBe(1);
});

test('owner can toggle active, reorder and delete', function () {
    $owner = owner();
    $category = ServiceCategory::factory()->create();
    $a = Service::factory()->create(['service_category_id' => $category->id, 'sort_order' => 1]);
    $b = Service::factory()->create(['service_category_id' => $category->id, 'sort_order' => 2]);

    $this->actingAs($owner)->patch("/services/{$a->id}", ['is_active' => false]);
    expect($a->fresh()->is_active)->toBeFalse();

    $this->actingAs($owner)->post('/services/reorder', ['ids' => [$b->id, $a->id]])->assertRedirect();
    expect($b->fresh()->sort_order)->toBe(1)->and($a->fresh()->sort_order)->toBe(2);

    $this->actingAs($owner)->delete("/services/{$a->id}")->assertRedirect();
    expect(Service::find($a->id))->toBeNull();

    // category with services can't be deleted
    $this->actingAs($owner)->delete("/service-categories/{$category->id}")->assertSessionHas('error');
    expect(ServiceCategory::find($category->id))->not->toBeNull();

    $this->actingAs($owner)->delete("/services/{$b->id}");
    $this->actingAs($owner)->delete("/service-categories/{$category->id}");
    expect(ServiceCategory::find($category->id))->toBeNull();
});

test('categories can be reordered', function () {
    $c1 = ServiceCategory::factory()->create(['sort_order' => 1]);
    $c2 = ServiceCategory::factory()->create(['sort_order' => 2]);

    $this->actingAs(owner())->post('/service-categories/reorder', ['ids' => [$c2->id, $c1->id]]);

    expect($c2->fresh()->sort_order)->toBe(1)->and($c1->fresh()->sort_order)->toBe(2);
});
