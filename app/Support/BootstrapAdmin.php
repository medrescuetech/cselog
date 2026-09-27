<?php

namespace App\Support;

use App\Models\User;
use RuntimeException;

class BootstrapAdmin
{
    public function ensure(): User
    {
        $username = strtolower(trim((string) config('hwrt.bootstrap_admin.username', 'admin')));
        if (str_contains($username, '@')) {
            $username = (string) strtok($username, '@');
        }
        if (! preg_match('/^[a-z0-9._-]{3,80}$/', $username)) {
            throw new RuntimeException('The configured bootstrap Admin username must be 3-80 letters, numbers, dots, underscores or hyphens.');
        }

        $password = (string) config('hwrt.bootstrap_admin.password', 'admin');
        if (app()->environment('production') && (strlen($password) < 16 || $password === 'admin')) {
            throw new RuntimeException('Set a unique HWRT_ADMIN_PASSWORD of at least 16 characters before the production upgrade.');
        }

        $admin = User::firstOrCreate(
            ['username' => $username],
            [
                'name' => config('hwrt.bootstrap_admin.name', 'Admin'),
                'email' => config('hwrt.bootstrap_admin.email'),
                'password' => $password,
                'role' => 'admin',
                'active' => true,
                'must_change_password' => true,
            ],
        );

        if (! $admin->isAdmin()) {
            throw new RuntimeException("Bootstrap username '{$username}' is already assigned to a non-admin account.");
        }

        return $admin;
    }
}
