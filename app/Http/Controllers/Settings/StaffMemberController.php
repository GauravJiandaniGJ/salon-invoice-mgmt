<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StaffMemberRequest;
use App\Models\Activity;
use App\Models\StaffMember;
use Illuminate\Http\RedirectResponse;

class StaffMemberController extends Controller
{
    public function store(StaffMemberRequest $request): RedirectResponse
    {
        $member = StaffMember::create([...$request->validated(), 'is_active' => true]);

        Activity::log('staff.created', 'Staff member added', $member, null, $member->name);

        return back()->with('success', 'Staff member added.');
    }

    public function update(StaffMemberRequest $request, StaffMember $staffMember): RedirectResponse
    {
        $staffMember->update($request->validated());

        Activity::log('staff.updated', $staffMember->is_active ? 'Updated' : 'Deactivated', $staffMember, null, $staffMember->name);

        return back()->with('success', 'Staff member updated.');
    }
}
