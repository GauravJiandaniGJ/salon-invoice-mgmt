<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StaffMemberRequest;
use App\Models\StaffMember;
use Illuminate\Http\RedirectResponse;

class StaffMemberController extends Controller
{
    public function store(StaffMemberRequest $request): RedirectResponse
    {
        StaffMember::create([...$request->validated(), 'is_active' => true]);

        return back()->with('success', 'Staff member added.');
    }

    public function update(StaffMemberRequest $request, StaffMember $staffMember): RedirectResponse
    {
        $staffMember->update($request->validated());

        return back()->with('success', 'Staff member updated.');
    }
}
