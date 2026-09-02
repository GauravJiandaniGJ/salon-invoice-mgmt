<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserRequest;
use App\Http\Requests\Settings\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UserController extends Controller
{
    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create([...$request->validated(), 'is_active' => true]);

        return back()->with('success', 'User added.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $me = $request->user();

        if ($user->is($me)) {
            if (array_key_exists('is_active', $data) && ! $data['is_active']) {
                return back()->with('error', 'You cannot deactivate your own account.');
            }
            if (isset($data['role']) && $data['role'] !== User::ROLE_OWNER) {
                return back()->with('error', 'You cannot remove your own owner role.');
            }
        }

        $losesOwner = ($user->role === User::ROLE_OWNER && $user->is_active)
            && ((isset($data['role']) && $data['role'] !== User::ROLE_OWNER)
                || (array_key_exists('is_active', $data) && ! $data['is_active']));

        if ($losesOwner && User::where('role', User::ROLE_OWNER)->where('is_active', true)->whereKeyNot($user->id)->doesntExist()) {
            return back()->with('error', 'At least one active owner is required.');
        }

        if (array_key_exists('password', $data) && ($data['password'] === null || $data['password'] === '')) {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with('success', isset($data['password']) ? 'Password reset.' : 'User updated.');
    }
}
