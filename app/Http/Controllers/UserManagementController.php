<?php

namespace App\Http\Controllers;

use App\Models\DtcAuditLog;
use App\Models\User;
use App\Services\ClaimStaffNotifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        $users = User::query()->orderBy('name')->paginate(25);

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        return view('users.create');
    }

    public function ownProfile(Request $request): View
    {
        return view('users.show', [
            'user' => $request->user(),
            'isOwnProfile' => true,
        ]);
    }

    public function editOwnProfile(Request $request): View
    {
        return view('users.edit', [
            'user' => $request->user(),
            'isOwnProfile' => true,
        ]);
    }

    public function updateOwnProfile(Request $request): RedirectResponse
    {
        return $this->saveProfile($request, $request->user(), 'profile.show');
    }

    public function show(User $user): View
    {
        return view('users.show', ['user' => $user, 'isOwnProfile' => false]);
    }

    public function edit(User $user): View
    {
        return view('users.edit', ['user' => $user, 'isOwnProfile' => false]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        return $this->saveProfile($request, $user, 'users.show');
    }

    private function saveProfile(Request $request, User $user, string $redirectRoute): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', \Illuminate\Validation\Rules\Password::min(12)],
        ]);
        $data['email'] = strtolower(trim($data['email']));

        $changedFields = [];
        foreach (['name', 'email'] as $field) {
            if ($user->{$field} !== $data[$field]) {
                $changedFields[] = $field;
            }
        }
        if (! empty($data['password'])) {
            $changedFields[] = 'password';
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        DB::transaction(function () use ($request, $user, $data, $changedFields) {
            $user->fill($data)->save();

            if ($changedFields !== []) {
                DtcAuditLog::record(
                    $request,
                    'user.account_updated',
                    'user',
                    $user->id,
                    null,
                    'Updated account details for '.$user->name.'.',
                    null,
                    ['changed_fields' => $changedFields]
                );
            }
        });

        return redirect()->route($redirectRoute, $redirectRoute === 'users.show' ? $user : [])
            ->with('status', 'Account updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'staff', 403);

        DB::transaction(function () use ($request, $user) {
            DB::table('claims')
                ->where('assigned_to', $user->id)
                ->update(['assigned_to' => null, 'assigned_at' => null, 'updated_at' => now()]);
            DB::table('claim_notification_recipients')->where('user_id', $user->id)->delete();
            $user->delete();

            DtcAuditLog::record(
                $request,
                'user.account_deleted',
                'user',
                $user->id,
                null,
                'Soft-deleted staff account '.$user->name.'.',
                null,
                ['role' => $user->role]
            );
        });

        return redirect()->route('users.index')->with('status', 'Staff account deleted successfully.');
    }

    public function store(Request $request, ClaimStaffNotifications $staffNotifications): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:staff,admin'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(12)],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
        ]);
        $user->forceFill(['role' => $data['role'], 'email_verified_at' => now()])->save();
        $staffNotifications->syncOutstandingSubmissionsFor($user);

        DtcAuditLog::record(
            $request,
            'user.account_created',
            'user',
            $user->id,
            null,
            'Created '.$data['role'].' account '.$user->name.'.',
            null,
            ['role' => $data['role']]
        );

        return redirect()->route('users.index')->with('status', 'Account created successfully.');
    }
}
