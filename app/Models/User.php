<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'active', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'bool',
            'must_change_password' => 'bool',
        ];
    }

    public const ROLES = ['user' => 0, 'admin' => 1];

    public function atLeast(string $role): bool
    {
        return (self::ROLES[$this->role] ?? -1) >= (self::ROLES[$role] ?? 99);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
