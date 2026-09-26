<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('settings.users', [
            'users' => User::query()
                ->orderByDesc('active')
                ->orderBy('name')
                ->orderBy('username')
                ->get(),
            'roles' => array_keys(User::ROLES),
        ]);
    }

    public function store(Request $request)
    {
        $this->normalizeUsername($request);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'username' => 'required|string|min:3|max:80|regex:/^[A-Za-z0-9._-]+$/|unique:users,username',
            'email' => 'nullable|email|max:255|unique:users,email',
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'active' => 'required|boolean',
            'password' => 'required|string|min:12|confirmed',
        ]);

        $data['email'] = blank($data['email'] ?? null) ? null : strtolower($data['email']);
        $data['username'] = strtolower($data['username']);

        $data['must_change_password'] = true;
        User::create($data);

        return redirect()->route('settings.users.index')
            ->with('status', "Created {$data['name']}.");
    }

    public function update(Request $request, User $user)
    {
        $this->normalizeUsername($request);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'username' => [
                'required', 'string', 'min:3', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'active' => 'required|boolean',
            'password' => 'nullable|string|min:12|confirmed',
        ]);

        $data['active'] = (bool) $data['active'];
        $data['email'] = blank($data['email'] ?? null) ? null : strtolower($data['email']);
        $data['username'] = strtolower($data['username']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $data['must_change_password'] = true;
        }

        $error = DB::transaction(function () use ($request, $user, $data): ?string {
            $activeAdmins = User::query()
                ->where('role', 'admin')
                ->where('active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->is($request->user()) && (! $data['active'] || $data['role'] !== 'admin')) {
                return 'You cannot deactivate or remove the Admin role from your own account.';
            }

            $removingActiveAdmin = $user->role === 'admin'
                && $user->active
                && (! $data['active'] || $data['role'] !== 'admin');

            if ($removingActiveAdmin && $activeAdmins->count() <= 1) {
                return 'At least one active Admin account must remain.';
            }

            $user->update($data);

            return null;
        }, attempts: 3);

        if ($error !== null) {
            return back()->withErrors(['user' => $error])->withInput();
        }

        return redirect()->route('settings.users.index')
            ->with('status', "Updated {$user->name}.");
    }

    private function normalizeUsername(Request $request): void
    {
        $username = $request->input('username');
        if (is_string($username)) {
            $request->merge(['username' => strtolower(trim($username))]);
        }
    }
}
