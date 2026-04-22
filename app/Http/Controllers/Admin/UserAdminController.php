<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserAdminController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'account_status' => ['required', Rule::enum(AccountStatus::class)],
            'supervisor_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', UserRole::Supervisor)),
            ],
        ]);

        if ($data['role'] === UserRole::Employee->value && empty($data['supervisor_id'])) {
            return back()->withErrors(['supervisor_id' => 'Assign a supervisor for employees.']);
        }

        $supervisorId = $data['role'] === UserRole::Employee->value ? $data['supervisor_id'] : null;

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'account_status' => $data['account_status'],
            'supervisor_id' => $supervisorId,
        ]);

        AuditLogger::log($request->user()->id, 'user.created', $user, null, $request);

        return back()->with('status', 'User created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'account_status' => ['required', Rule::enum(AccountStatus::class)],
            'supervisor_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', UserRole::Supervisor)),
            ],
        ]);

        if ($data['role'] === UserRole::Employee->value && empty($data['supervisor_id'])) {
            return back()->withErrors(['supervisor_id' => 'Assign a supervisor for employees.']);
        }

        $supervisorId = $data['role'] === UserRole::Employee->value ? $data['supervisor_id'] : null;

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'account_status' => $data['account_status'],
            'supervisor_id' => $supervisorId,
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);

        AuditLogger::log($request->user()->id, 'user.updated', $user, null, $request);

        return back()->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403);

        $user->delete();

        AuditLogger::log($request->user()->id, 'user.deleted', null, ['deleted_user_id' => $user->id], $request);

        return back()->with('status', 'User removed.');
    }
}
