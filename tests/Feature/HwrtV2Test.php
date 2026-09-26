<?php

namespace Tests\Feature;

use App\Models\Landmark;
use App\Models\Location;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HwrtV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_change_theme_and_manage_work_types(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post('/settings/appearance', ['theme' => 'light'])
            ->assertRedirect();

        $this->assertSame('light', Setting::value('appearance.theme'));

        $this->actingAs($admin)->post('/settings/work-types', [
            'name' => 'Pressure Testing',
            'colour' => '#123456',
            'sort_order' => 20,
            'notes_prompt' => 'Pressure / exclusion zone',
            'active' => '1',
            'requires_note' => '1',
            'is_default' => '0',
            'is_other' => '0',
        ])->assertRedirect();

        $type = WorkType::where('name', 'Pressure Testing')->firstOrFail();
        $this->assertTrue($type->active);
        $this->assertTrue($type->requires_note);
        $this->assertSame('Pressure / exclusion zone', $type->notes_prompt);
    }

    public function test_location_pdf_is_private_and_available_to_authenticated_users(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $location = Location::create([
            'name' => 'Vessel A',
            'easting' => 476100,
            'northing' => 7718100,
        ]);

        $pdf = UploadedFile::fake()->create('vessel-a-plan.pdf', 128, 'application/pdf');

        $this->actingAs($admin)
            ->post("/settings/locations/{$location->id}/document", ['document' => $pdf])
            ->assertRedirect();

        $location->refresh();
        $this->assertNotNull($location->document_path);
        $this->assertSame('vessel-a-plan.pdf', $location->document_name);
        Storage::disk('local')->assertExists($location->document_path);

        $this->get("/locations/{$location->id}/document")->assertRedirect('/login');

        $this->actingAs($user)
            ->get("/locations/{$location->id}/document")
            ->assertOk()
            ->assertDownload('vessel-a-plan.pdf');
    }

    public function test_admin_can_add_manual_landmark(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/settings/landmarks', [
            'name' => 'Emergency Assembly Point',
            'category' => 'emergency',
            'easting' => 476250.123,
            'northing' => 7718450.456,
            'active' => '1',
        ])->assertRedirect();

        $landmark = Landmark::where('name', 'Emergency Assembly Point')->firstOrFail();
        $this->assertSame('manual', $landmark->source);
        $this->assertEqualsWithDelta(476250.123, $landmark->easting, 0.001);
        $this->assertTrue($landmark->active);
    }

    public function test_map_refresh_request_is_queued_without_running_inside_http_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post('/settings/map/refresh')
            ->assertRedirect();

        $this->assertSame('queued', Setting::value('map.last_status'));
        $this->assertNotEmpty(Setting::value('map.refresh_requested_at'));
    }

    public function test_login_and_map_use_local_frontend_assets(): void
    {
        $this->assertFileExists(public_path('css/app.css'));
        $this->assertFileExists(public_path('vendor/alpinejs/alpine.min.js'));
        $this->assertFileExists(public_path('vendor/leaflet/leaflet.js'));

        $this->get('/login')
            ->assertOk()
            ->assertSee(asset('css/app.css'))
            ->assertDontSee('cdn.tailwindcss.com')
            ->assertDontSee('cdn.jsdelivr.net')
            ->assertDontSee('unpkg.com');

        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user)
            ->get('/map')
            ->assertOk()
            ->assertSee(asset('vendor/leaflet/leaflet.css'))
            ->assertSee(asset('vendor/leaflet/leaflet.js'))
            ->assertDontSee('unpkg.com');
    }

    public function test_sitemap_import_validates_all_sources_before_mutating_database(): void
    {
        Storage::fake('local');
        $path = Storage::disk('local')->path('testing-map-package');
        $features = $path.DIRECTORY_SEPARATOR.'features';
        mkdir($features, 0777, true);

        $validArea = [
            'type' => 'FeatureCollection',
            'crs_epsg' => 28350,
            'source' => 'test',
            'features' => [[
                'type' => 'Feature',
                'properties' => ['Name' => 'Test boundary'],
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [[[0, 0], [0, 1], [1, 1], [1, 0], [0, 0]]],
                ],
            ]],
        ];
        file_put_contents($features.'/project-development-envelope.geojson', json_encode($validArea));
        file_put_contents($features.'/lease-boundaries.geojson', json_encode([
            'type' => 'FeatureCollection',
            'crs_epsg' => 4326,
            'features' => [],
        ]));
        file_put_contents($features.'/project-boundaries-infrastructure.geojson', json_encode([
            'type' => 'FeatureCollection',
            'crs_epsg' => 28350,
            'features' => [],
        ]));

        $this->assertSame(1, Artisan::call('sitemap:import', ['--path' => $path]));
        $this->assertDatabaseCount('areas', 0);
    }
}
