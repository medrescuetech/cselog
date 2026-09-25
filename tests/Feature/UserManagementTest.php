<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_access_user_settings(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/settings/users')->assertForbidden();

        $this->actingAs($admin)
            ->get('/settings/users')
            ->assertOk()
            ->assertSee('Settings · Users')
            ->assertSee($user->username);
    }

    public function test_admin_can_create_username_only_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/settings/users', [
            'name' => 'Control Room',
            'username' => 'control.room',
            'email' => '',
            'role' => 'user',
            'active' => '1',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])->assertRedirect(route('settings.users.index'));

        $user = User::where('username', 'control.room')->firstOrFail();
        $this->assertSame('Control Room', $user->name);
        $this->assertNull($user->email);
        $this->assertSame('user', $user->role);
        $this->assertTrue($user->active);
        $this->assertTrue(Hash::check('StrongPassword123!', $user->password));
    }

    public function test_admin_can_change_status_and_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'role' => 'user',
            'active' => true,
            'password' => 'OriginalPassword123!',
        ]);

        $this->actingAs($admin)->patch("/settings/users/{$user->id}", [
            'name' => 'Updated User',
            'username' => 'updated.user',
            'email' => '',
            'role' => 'user',
            'active' => '0',
            'password' => 'ReplacementPassword123!',
            'password_confirmation' => 'ReplacementPassword123!',
        ])->assertRedirect(route('settings.users.index'));

        $user->refresh();
        $this->assertSame('Updated User', $user->name);
        $this->assertSame('updated.user', $user->username);
        $this->assertFalse($user->active);
        $this->assertTrue(Hash::check('ReplacementPassword123!', $user->password));
    }

    public function test_admin_cannot_deactivate_or_demote_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);

        $this->actingAs($admin)->patch("/settings/users/{$admin->id}", [
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => $admin->email,
            'role' => 'user',
            'active' => '0',
            'password' => '',
            'password_confirmation' => '',
        ])->assertSessionHasErrors('user');

        $admin->refresh();
        $this->assertTrue($admin->active);
        $this->assertSame('admin', $admin->role);
    }

    public function test_admin_can_deactivate_another_admin_when_one_remains(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $other = User::factory()->create(['role' => 'admin', 'active' => true]);

        $this->actingAs($admin)->patch("/settings/users/{$other->id}", [
            'name' => $other->name,
            'username' => $other->username,
            'email' => $other->email,
            'role' => 'admin',
            'active' => '0',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect(route('settings.users.index'));

        $this->assertFalse($other->fresh()->active);
        $this->assertTrue($admin->fresh()->active);
    }
}
