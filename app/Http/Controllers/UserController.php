<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
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

        User::create($data);

        return redirect()->route('settings.users.index')
            ->with('status', "Created {$data['name']}.");
    }

    public function update(Request $request, User $user)
    {
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

        if ($user->is($request->user()) && (! $data['active'] || $data['role'] !== 'admin')) {
            return back()->withErrors([
                'user' => 'You cannot deactivate or remove the Admin role from your own account.',
            ])->withInput();
        }

        $removingActiveAdmin = $user->role === 'admin'
            && $user->active
            && (! $data['active'] || $data['role'] !== 'admin');

        if ($removingActiveAdmin
            && User::query()->where('role', 'admin')->where('active', true)->count() <= 1) {
            return back()->withErrors([
                'user' => 'At least one active Admin account must remain.',
            ])->withInput();
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('settings.users.index')
            ->with('status', "Updated {$user->name}.");
    }
}
