<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
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
        $this->assertTrue($user->must_change_password);
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
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('ReplacementPassword123!', $user->password));
    }

    public function test_database_default_role_is_user(): void
    {
        $user = User::create([
            'name' => 'Default Role User',
            'username' => 'default.role',
            'password' => 'StrongPassword123!',
            'active' => true,
        ]);

        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_seed_adds_rotatable_admin_without_overwriting_existing_admin(): void
    {
        config(['hwrt.bootstrap_admin' => [
            'name' => 'Admin',
            'username' => 'admin',
            'email' => null,
            'password' => 'admin',
        ]]);

        $existing = User::factory()->create([
            'name' => 'Existing Admin',
            'username' => 'existing.admin',
            'role' => 'admin',
            'password' => 'ExistingSecurePassword123!',
        ]);

        $this->seed(DatabaseSeeder::class);

        $existing->refresh();
        $this->assertSame('Existing Admin', $existing->name);
        $this->assertSame('admin', $existing->role);
        $this->assertTrue(Hash::check('ExistingSecurePassword123!', $existing->password));

        $bootstrap = User::where('username', 'admin')->firstOrFail();
        $this->assertSame('admin', $bootstrap->role);
        $this->assertTrue($bootstrap->active);
        $this->assertTrue($bootstrap->must_change_password);
        $this->assertTrue(Hash::check('admin', $bootstrap->password));

        $this->artisan('hwrt:bootstrap-admin')->assertExitCode(0);
        $this->assertTrue(Hash::check('admin', $bootstrap->fresh()->password));
        $this->assertTrue($bootstrap->fresh()->must_change_password);
    }

    public function test_bootstrap_admin_password_must_change_before_app_access(): void
    {
        config(['hwrt.bootstrap_admin' => [
            'name' => 'Admin',
            'username' => 'admin',
            'email' => null,
            'password' => 'admin',
        ]]);
        $this->seed(DatabaseSeeder::class);

        $this->post('/login', ['login' => 'admin', 'password' => 'admin'])
            ->assertRedirect(route('password.change'));
        $this->get('/board')->assertRedirect(route('password.change'));
        $this->get('/password/change')->assertOk()->assertSee('Change your password');

        $this->post('/password/change', [
            'current_password' => 'admin',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])->assertRedirect(route('board'));

        $this->assertFalse(User::where('username', 'admin')->firstOrFail()->must_change_password);
        $this->get('/board')->assertOk();
    }

    public function test_bootstrap_credentials_can_be_overridden(): void
    {
        config(['hwrt.bootstrap_admin' => [
            'name' => 'Configured Admin',
            'username' => 'ops.admin',
            'email' => 'ops@example.com',
            'password' => 'ConfiguredPassword123!',
        ]]);

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('username', 'ops.admin')->firstOrFail();
        $this->assertSame('Configured Admin', $admin->name);
        $this->assertSame('ops@example.com', $admin->email);
        $this->assertTrue(Hash::check('ConfiguredPassword123!', $admin->password));
        $this->assertTrue($admin->must_change_password);
    }

    public function test_bootstrap_command_does_not_reset_existing_admin_credentials(): void
    {
        config(['hwrt.bootstrap_admin' => [
            'name' => 'New bootstrap name',
            'username' => 'admin',
            'email' => null,
            'password' => 'admin',
        ]]);
        $existing = User::factory()->create([
            'name' => 'Existing setup Admin',
            'username' => 'admin',
            'role' => 'admin',
            'password' => 'ExistingSecurePassword123!',
            'must_change_password' => false,
        ]);

        $this->artisan('hwrt:bootstrap-admin')->assertExitCode(0);

        $existing->refresh();
        $this->assertSame('Existing setup Admin', $existing->name);
        $this->assertFalse($existing->must_change_password);
        $this->assertTrue(Hash::check('ExistingSecurePassword123!', $existing->password));
    }

    public function test_upgrade_reserves_admin_username_and_adds_rotatable_bootstrap_account(): void
    {
        config(['hwrt.bootstrap_admin' => [
            'name' => 'Admin',
            'username' => 'admin',
            'email' => null,
            'password' => 'admin',
        ]]);
        $legacy = User::factory()->create([
            'name' => 'Existing Admin',
            'username' => 'admin',
            'email' => 'existing.admin@example.com',
            'role' => 'admin',
            'password' => 'ExistingSecurePassword123!',
        ]);

        $migration = require base_path('database/migrations/2026_09_26_000000_reserve_bootstrap_admin_username.php');
        $migration->up();
        $this->artisan('hwrt:bootstrap-admin')->assertExitCode(0);

        $legacy->refresh();
        $this->assertSame('legacy-admin-'.$legacy->id, $legacy->username);
        $this->assertSame('admin', $legacy->role);
        $this->assertSame('existing.admin@example.com', $legacy->email);
        $this->assertTrue(Hash::check('ExistingSecurePassword123!', $legacy->password));

        $bootstrap = User::where('username', 'admin')->firstOrFail();
        $this->assertSame('admin', $bootstrap->role);
        $this->assertTrue($bootstrap->must_change_password);
        $this->assertTrue(Hash::check('admin', $bootstrap->password));
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

    public function test_last_active_admin_remains_after_user_deactivation(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $other = User::factory()->create(['role' => 'admin', 'active' => true]);

        $this->actingAs($admin)->patch("/settings/users/{$other->id}", [
            'name' => $other->name,
            'username' => $other->username,
            'email' => $other->email,
            'role' => 'user',
            'active' => '0',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect(route('settings.users.index'));

        $this->assertSame(1, User::query()->where('role', 'admin')->where('active', true)->count());
        $this->assertTrue($admin->fresh()->active);
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

    public function test_username_is_normalized_before_uniqueness_validation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $existing = User::factory()->create(['username' => 'operator']);
        User::factory()->create(['username' => 'admin']);

        $this->actingAs($admin)->post('/settings/users', [
            'name' => 'Duplicate',
            'username' => 'ADMIN',
            'email' => '',
            'role' => 'user',
            'active' => '1',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])->assertSessionHasErrors('username');

        $this->patch("/settings/users/{$existing->id}", [
            'name' => $existing->name,
            'username' => 'ADMIN',
            'email' => $existing->email,
            'role' => 'user',
            'active' => '1',
            'password' => '',
            'password_confirmation' => '',
        ])->assertSessionHasErrors('username');
    }
}
