<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    private User $mapOnlyUser;
    private User $viewerUser;
    private User $loggerUser;
    private User $supervisorUser;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapOnlyUser = User::factory()->create(['role' => 'map_only']);
        $this->viewerUser = User::factory()->create(['role' => 'viewer']);
        $this->loggerUser = User::factory()->create(['role' => 'logger']);
        $this->supervisorUser = User::factory()->create(['role' => 'supervisor']);
        $this->adminUser = User::factory()->create(['role' => 'admin']);
    }

    public function test_map_only_user_can_access_map_and_api_but_not_board_or_history(): void
    {
        $this->actingAs($this->mapOnlyUser)
            ->get('/map')
            ->assertOk();

        $this->actingAs($this->mapOnlyUser)
            ->get('/api/open')
            ->assertOk();

        $this->actingAs($this->mapOnlyUser)
            ->get('/board')
            ->assertForbidden();

        $this->actingAs($this->mapOnlyUser)
            ->get('/history')
            ->assertForbidden();
    }

    public function test_viewer_can_access_board_history_and_map_but_not_log(): void
    {
        $this->actingAs($this->viewerUser)
            ->get('/map')
            ->assertOk();

        $this->actingAs($this->viewerUser)
            ->get('/board')
            ->assertOk();

        $this->actingAs($this->viewerUser)
            ->get('/history')
            ->assertOk();

        $this->actingAs($this->viewerUser)
            ->get('/log')
            ->assertForbidden();
    }

    public function test_logger_can_access_log_form_and_store_entries(): void
    {
        $this->actingAs($this->loggerUser)
            ->get('/log')
            ->assertOk();

        $this->actingAs($this->loggerUser)
            ->get('/admin/locations')
            ->assertForbidden();
    }

    public function test_supervisor_can_access_admin_locations(): void
    {
        $this->actingAs($this->supervisorUser)
            ->get('/admin/locations')
            ->assertOk();

        $this->actingAs($this->supervisorUser)
            ->get('/admin/settings')
            ->assertForbidden();
    }

    public function test_admin_can_access_settings_and_update_roles_and_work_types(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/admin/settings')
            ->assertOk();

        $targetUser = User::factory()->create(['role' => 'viewer']);

        $this->actingAs($this->adminUser)
            ->post("/admin/settings/users/{$targetUser->id}/role", [
                'role' => 'supervisor',
            ])
            ->assertRedirect();

        $this->assertEquals('supervisor', $targetUser->fresh()->role);

        $this->actingAs($this->adminUser)
            ->post('/admin/settings/work-types', [
                'name' => 'High Pressure Water Jetting',
                'colour' => '#10b981',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('work_types', ['name' => 'High Pressure Water Jetting']);
    }
}
