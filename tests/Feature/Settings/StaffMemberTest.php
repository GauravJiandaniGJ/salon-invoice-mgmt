<?php

use App\Models\StaffMember;

test('staff cannot manage staff members', function () {
    $this->actingAs(staff())->post('/settings/staff-members', ['name' => 'Asha'])->assertForbidden();
});

test('owner can add, rename and deactivate a staff member', function () {
    $owner = owner();

    $this->actingAs($owner)->post('/settings/staff-members', ['name' => 'Asha'])->assertRedirect()->assertSessionHasNoErrors();
    $member = StaffMember::where('name', 'Asha')->firstOrFail();
    expect($member->is_active)->toBeTrue();

    $this->actingAs($owner)->patch("/settings/staff-members/{$member->id}", ['name' => 'Asha K'])->assertSessionHasNoErrors();
    expect($member->fresh()->name)->toBe('Asha K');

    $this->actingAs($owner)->patch("/settings/staff-members/{$member->id}", ['is_active' => false]);
    expect($member->fresh()->is_active)->toBeFalse();

    $this->actingAs($owner)->post('/settings/staff-members', ['name' => ''])->assertSessionHasErrors('name');
});
