<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_access_user_management(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($viewer)->get('/admin/users')->assertForbidden();
        $this->actingAs($supervisor)->get('/admin/users')->assertForbidden();

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('User management')
            ->assertSee($viewer->email);
    }

    public function test_admin_can_create_user_with_role_and_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Site Supervisor',
            'email' => 'supervisor@example.com',
            'role' => 'supervisor',
            'active' => '1',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'supervisor@example.com')->firstOrFail();
        $this->assertSame('Site Supervisor', $user->name);
        $this->assertSame('supervisor', $user->role);
        $this->assertTrue($user->active);
        $this->assertTrue(Hash::check('StrongPassword123!', $user->password));
    }

    public function test_admin_can_change_role_status_and_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'role' => 'logger',
            'active' => true,
            'password' => 'OriginalPassword123!',
        ]);

        $this->actingAs($admin)->patch("/admin/users/{$user->id}", [
            'name' => 'Updated User',
            'email' => $user->email,
            'role' => 'viewer',
            'active' => '0',
            'password' => 'ReplacementPassword123!',
            'password_confirmation' => 'ReplacementPassword123!',
        ])->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertSame('Updated User', $user->name);
        $this->assertSame('viewer', $user->role);
        $this->assertFalse($user->active);
        $this->assertTrue(Hash::check('ReplacementPassword123!', $user->password));
    }

    public function test_admin_cannot_deactivate_or_demote_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);

        $this->actingAs($admin)->patch("/admin/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'viewer',
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

        $this->actingAs($admin)->patch("/admin/users/{$other->id}", [
            'name' => $other->name,
            'email' => $other->email,
            'role' => 'admin',
            'active' => '0',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertFalse($other->fresh()->active);
        $this->assertTrue($admin->fresh()->active);
    }
}
