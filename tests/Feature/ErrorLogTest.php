<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorLogTest extends TestCase
{
    use RefreshDatabase;

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logPath = storage_path('logs/hwrt-errors.log');
        @unlink($this->logPath);
    }

    protected function tearDown(): void
    {
        @unlink($this->logPath);
        parent::tearDown();
    }

    public function test_browser_errors_are_written_to_dedicated_log(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->postJson('/api/client-errors', [
            'kind' => 'map-warning',
            'message' => 'Test raster failed',
            'source' => '/sitemap/test.jpg',
            'line' => 42,
            'context' => ['layer' => 'test'],
        ])->assertAccepted()->assertJson(['ok' => true]);

        $this->assertFileExists($this->logPath);
        $log = file_get_contents($this->logPath);
        $this->assertStringContainsString('Browser error: Test raster failed', $log);
        $this->assertStringContainsString('map-warning', $log);
        $this->assertStringContainsString('/sitemap/test.jpg', $log);
    }

    public function test_error_page_is_admin_only_and_can_download_log(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);

        file_put_contents($this->logPath, "[test] visible error line\n");

        $this->actingAs($user)->get('/error')->assertForbidden();

        $this->actingAs($admin)
            ->get('/error')
            ->assertOk()
            ->assertSee('Error log')
            ->assertSee('visible error line');

        $this->actingAs($admin)
            ->get('/error/download')
            ->assertOk()
            ->assertDownload('hwrt-errors.log');
    }

    public function test_admin_can_clear_error_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        file_put_contents($this->logPath, "old error\n");

        $this->actingAs($admin)
            ->post('/error/clear')
            ->assertRedirect(route('errors.index'));

        $log = file_get_contents($this->logPath);
        $this->assertStringNotContainsString('old error', $log);
        $this->assertStringContainsString('Error log cleared by administrator.', $log);
    }
}
