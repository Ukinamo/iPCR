<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SupervisorTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserAdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::query()->orderBy('name')->get(),
            'pendingCount' => User::query()->where('account_status', AccountStatus::Pending)->count(),
        ]);
    }

    public function pending(): Response
    {
        return Inertia::render('Admin/Users/Pending', [
            'pendingUsers' => User::query()
                ->where('account_status', AccountStatus::Pending)
                ->orderBy('created_at')
                ->get(),
        ]);
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        abort_if($user->account_status !== AccountStatus::Pending, 404);
        abort_if($user->role !== UserRole::Employee, 404);

        $user->update([
            'account_status' => AccountStatus::Active,
        ]);

        AuditLogger::log($request->user()->id, 'user.registration.approved', $user, null, $request);

        return to_route('admin.users.pending')->with('status', 'Registration approved. The employee can now sign in.');
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        abort_if($user->account_status !== AccountStatus::Pending, 404);

        AuditLogger::log($request->user()->id, 'user.registration.rejected', null, [
            'rejected_user_id' => $user->id,
            'rejected_email' => $user->email,
        ], $request);

        $user->delete();

        return to_route('admin.users.pending')->with('status', 'Registration rejected and removed.');
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'account_status' => ['required', Rule::enum(AccountStatus::class)],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'account_status' => $data['account_status'],
            'supervisor_id' => null,
        ]);

        AuditLogger::log($request->user()->id, 'user.created', $user, null, $request);

        return to_route('admin.users.index')->with('status', 'User created.');
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

        if ($data['role'] !== UserRole::Employee->value) {
            $supervisorId = null;
        } elseif ($request->exists('supervisor_id')) {
            $supervisorId = $data['supervisor_id'] ?: null;
        } else {
            $supervisorId = $user->supervisor_id;
        }

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

        $previousSupervisorId = $user->supervisor_id;

        $user->update($payload);

        AuditLogger::log($request->user()->id, 'user.updated', $user, null, $request);

        $fresh = $user->fresh();
        if (
            $fresh->isEmployee()
            && $fresh->supervisor_id
            && (int) $fresh->supervisor_id !== (int) $previousSupervisorId
        ) {
            app(SupervisorTransferService::class)->reassignEmployeeToSupervisor(
                $fresh,
                (int) $fresh->supervisor_id,
            );
        }

        return to_route('admin.users.index')->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403);

        $user->delete();

        AuditLogger::log($request->user()->id, 'user.deleted', null, ['deleted_user_id' => $user->id], $request);

        return to_route('admin.users.index')->with('status', 'User removed.');
    }
}
