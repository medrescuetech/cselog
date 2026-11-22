<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Entry;
use App\Models\Landmark;
use App\Models\Location;
use App\Models\MapSector;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkTag;
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

        $this->post('/logout');
        $this->get("/locations/{$location->id}/document")->assertRedirect('/login');

        $this->actingAs($user)
            ->get("/locations/{$location->id}/document")
            ->assertOk()
            ->assertDownload('vessel-a-plan.pdf');
    }

    public function test_admin_can_verify_and_unverify_location(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $location = Location::create(['name' => 'Pin 7', 'easting' => 476100, 'northing' => 7718100]);

        $this->assertFalse($location->refresh()->verified);

        $this->actingAs($user)
            ->patch("/settings/locations/{$location->id}/verify", ['verified' => 1])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch("/settings/locations/{$location->id}/verify", ['verified' => 1])
            ->assertRedirect();
        $this->assertTrue($location->refresh()->verified);

        $this->actingAs($admin)
            ->get('/settings/locations?unverified=1')
            ->assertOk()
            ->assertViewHas('locations', fn ($locations) => $locations->getCollection()->doesntContain('id', $location->id));

        $this->actingAs($admin)
            ->patch("/settings/locations/{$location->id}/verify", ['verified' => 0])
            ->assertRedirect();
        $this->assertFalse($location->refresh()->verified);
    }

    public function test_admin_can_add_manual_landmark(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/settings/landmarks', [
            'name' => 'Emergency Assembly Point',
            'category' => 'emergency',
            'marker_type' => 'muster_point',
            'colour' => '#22c55e',
            'easting' => 476250.123,
            'northing' => 7718450.456,
            'active' => '1',
            'label_visible' => '1',
            'rotation_deg' => '0',
        ])->assertRedirect();

        $landmark = Landmark::where('name', 'Emergency Assembly Point')->firstOrFail();
        $this->assertSame('manual', $landmark->source);
        $this->assertSame('muster_point', $landmark->marker_type);
        $this->assertEqualsWithDelta(476250.123, $landmark->easting, 0.001);
        $this->assertTrue($landmark->active);
    }

    public function test_settings_forms_use_standard_colour_picker(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['/settings/landmarks', '/settings/work-types'] as $uri) {
            $this->actingAs($admin)
                ->get($uri)
                ->assertOk()
                ->assertSee('hwrt-colour-picker.js')
                ->assertSee('colourPicker(', false)
                ->assertSee('type="range"', false)
                ->assertDontSee('type="color"', false);
        }
    }

    public function test_admin_can_save_default_map_view(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->postJson('/settings/map/view', ['easting' => 476250.5, 'northing' => 7718450.25, 'zoom' => 1.5])
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson('/settings/map/view', ['easting' => 476250.5, 'northing' => 7718450.25, 'zoom' => 1.5])
            ->assertOk()
            ->assertJsonPath('default_view.easting', 476250.5)
            ->assertJsonPath('default_view.northing', 7718450.25)
            ->assertJsonPath('default_view.zoom', 1.5);

        $this->actingAs($user)
            ->getJson('/api/layers')
            ->assertOk()
            ->assertJsonPath('default_view.easting', 476250.5)
            ->assertJsonPath('default_view.northing', 7718450.25)
            ->assertJsonPath('default_view.zoom', 1.5);
    }

    public function test_demo_dataset_can_be_seeded_and_removed(): void
    {
        $this->artisan('hwrt:demo-seed', ['--password' => true])
            ->expectsQuestion('Demo user password (minimum 12 characters)', 'demo-secret-12345')
            ->assertExitCode(0);
        $firstDemoAdminId = User::where('username', 'demo.admin')->value('id');

        $realUser = User::create([
            'name' => 'Real User', 'username' => 'real.user', 'email' => 'real@example.test',
            'password' => 'real-secret-12345', 'role' => 'user', 'active' => true,
        ]);
        $realArea = Area::create([
            'name' => 'Real Area', 'kind' => 'zone', 'geometry' => [
                'type' => 'Polygon', 'coordinates' => [[[1, 1], [2, 1], [2, 2], [1, 1]]],
            ], 'source' => 'manual', 'active' => true,
        ]);
        $realLocation = Location::create([
            'name' => 'Real Location', 'code' => 'REAL-01', 'easting' => 476000,
            'northing' => 7718000, 'area_id' => $realArea->id, 'status' => 'active',
            'verified' => true, 'created_by' => $realUser->id,
        ]);
        $realTag = WorkTag::create(['name' => 'Real tag', 'active' => true]);
        $realEntry = Entry::create([
            'location_id' => $realLocation->id, 'location_label' => $realLocation->name,
            'easting' => $realLocation->easting, 'northing' => $realLocation->northing,
            'area_id' => $realArea->id, 'work_type_id' => WorkType::where('name', 'Inspection')->value('id'),
            'notes' => 'Real entry', 'permit_no' => 'REAL-001', 'status' => 'open',
            'opened_at' => now(), 'opened_by' => $realUser->id,
        ]);

        $this->artisan('hwrt:demo-seed', ['--password' => true])
            ->expectsQuestion('Demo user password (minimum 12 characters)', 'demo-secret-12345')
            ->assertExitCode(0);
        $this->assertNotSame($firstDemoAdminId, User::where('username', 'demo.admin')->value('id'));

        $this->assertTrue(User::where('username', 'demo.admin')->exists());
        $this->assertSame(5, Location::where('name', 'like', '[DEMO]%')->count());
        $this->assertSame(5, Landmark::where('source', 'demo')->count());
        $this->assertSame(2, MapSector::where('name', 'like', '[DEMO]%')->count());
        $this->assertSame(2, Area::where('source', 'demo')->count());
        $this->assertSame(11, Entry::where('permit_no', 'like', 'DEMO-%')->count());

        $demoUser = User::where('username', 'demo.logger')->firstOrFail();
        $this->actingAs($demoUser)
            ->getJson('/api/open')
            ->assertOk()
            ->assertJsonFragment(['permit_no' => 'DEMO-1001']);
        $this->actingAs($demoUser)
            ->get('/demo')
            ->assertRedirect('/board');

        $this->artisan('hwrt:demo-clear', ['--force' => true])
            ->assertExitCode(0);

        $this->assertFalse(User::where('username', 'like', 'demo.%')->exists());
        $this->assertFalse(Location::where('name', 'like', '[DEMO]%')->exists());
        $this->assertFalse(Landmark::where('source', 'demo')->exists());
        $this->assertFalse(MapSector::where('name', 'like', '[DEMO]%')->exists());
        $this->assertFalse(Area::where('source', 'demo')->exists());
        $this->assertFalse(WorkTag::where('name', 'like', 'DEMO:%')->exists());
        $this->assertFalse(Entry::where('permit_no', 'like', 'DEMO-%')->exists());
        $this->assertTrue(WorkType::where('name', 'Confined Space Entry')->exists());
        $this->assertTrue(User::whereKey($realUser->id)->exists());
        $this->assertTrue(Location::whereKey($realLocation->id)->exists());
        $this->assertTrue(Area::whereKey($realArea->id)->exists());
        $this->assertTrue(WorkTag::whereKey($realTag->id)->exists());
        $this->assertTrue(Entry::whereKey($realEntry->id)->exists());
    }

    public function test_static_map_overlays_can_be_hidden_and_apis_return_only_active_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin)->post('/settings/landmarks', [
            'name' => 'Main Access Road',
            'category' => 'roads',
            'marker_type' => 'road_label',
            'colour' => '#f8fafc',
            'easting' => 476300,
            'northing' => 7718500,
            'active' => '1',
            'label_visible' => '1',
            'rotation_deg' => '-24',
        ])->assertRedirect();
        $road = Landmark::where('name', 'Main Access Road')->firstOrFail();

        $this->actingAs($admin)->post('/settings/map-sectors', [
            'name' => 'Invalid Sector',
            'colour' => '#38bdf8',
            'fill_opacity' => '0.12',
            'sort_order' => '10',
            'active' => '1',
            'geometry' => json_encode(['type' => 'Polygon', 'coordinates' => [[[476100, 7718400], [476200, 7718400]]]]),
        ])->assertSessionHasErrors('geometry');

        $this->actingAs($admin)->post('/settings/map-sectors', [
            'name' => 'Sector A',
            'colour' => '#38bdf8',
            'fill_opacity' => '0.12',
            'sort_order' => '10',
            'active' => '1',
            'geometry' => json_encode([
                'type' => 'Polygon',
                'coordinates' => [[[476100, 7718400], [476200, 7718400], [476200, 7718500], [476100, 7718400]]],
            ]),
        ])->assertRedirect();
        $sector = MapSector::where('name', 'Sector A')->firstOrFail();

        $this->actingAs($admin)->patch("/settings/landmarks/{$road->id}", [
            'name' => 'Main Access Road',
            'category' => 'roads',
            'marker_type' => 'road_label',
            'colour' => '#f8fafc',
            'easting' => 476300,
            'northing' => 7718500,
            'active' => '0',
            'label_visible' => '1',
            'rotation_deg' => '-24',
        ])->assertRedirect();
        $this->actingAs($admin)->patch("/settings/map-sectors/{$sector->id}", [
            'name' => 'Sector A',
            'colour' => '#38bdf8',
            'fill_opacity' => '0.12',
            'sort_order' => '10',
            'active' => '0',
            'geometry' => json_encode($sector->geometry),
        ])->assertRedirect();

        Landmark::create([
            'name' => 'Muster Point B',
            'category' => 'muster',
            'marker_type' => 'muster_point',
            'colour' => '#22c55e',
            'easting' => 476400,
            'northing' => 7718600,
            'active' => true,
            'label_visible' => true,
            'source' => 'manual',
        ]);
        MapSector::create([
            'name' => 'Sector B',
            'colour' => '#f97316',
            'fill_opacity' => 0.15,
            'sort_order' => 20,
            'active' => true,
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[[476300, 7718500], [476400, 7718500], [476400, 7718600], [476300, 7718500]]],
            ],
        ]);

        $this->actingAs($user)
            ->getJson('/api/landmarks')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Muster Point B', 'marker_type' => 'muster_point'])
            ->assertJsonMissing(['name' => 'Main Access Road']);

        $this->actingAs($user)
            ->getJson('/api/map-sectors')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Sector B'])
            ->assertJsonMissing(['name' => 'Sector A']);
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

    public function test_sitemap_import_stores_local_road_features_when_available(): void
    {
        Storage::fake('local');
        $path = Storage::disk('local')->path('testing-map-package');
        $features = $path.DIRECTORY_SEPARATOR.'features';
        mkdir($features, 0777, true);

        foreach (['project-development-envelope.geojson', 'lease-boundaries.geojson', 'project-boundaries-infrastructure.geojson'] as $file) {
            file_put_contents($features.'/'.$file, json_encode([
                'type' => 'FeatureCollection',
                'crs_epsg' => 28350,
                'features' => [],
            ]));
        }
        file_put_contents($features.'/site-c-roads.geojson', json_encode([
            'type' => 'FeatureCollection',
            'crs_epsg' => 28350,
            'source' => 'test',
            'features' => [[
                'type' => 'Feature',
                'properties' => ['Layer' => 'R-CENTRELINE', 'OBJECTID' => 1],
                'geometry' => ['type' => 'LineString', 'coordinates' => [[476100, 7718400], [476200, 7718500]]],
            ]],
        ]));

        $this->assertSame(0, Artisan::call('sitemap:import', ['--path' => $path]));
        $this->assertDatabaseHas('areas', [
            'kind' => 'road',
            'name' => 'R-Centreline 1',
            'source_ref' => '1',
        ]);
    }

    public function test_user_can_add_and_remove_poi_landmarks(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->postJson('/api/landmarks', [
            'name' => 'Hydration Station A',
            'marker_type' => 'hydration',
            'easting' => 476300,
            'northing' => 7718500,
        ]);
        $response->assertCreated()
            ->assertJsonFragment([
                'name' => 'Hydration Station A',
                'marker_type' => 'hydration',
                'category' => 'poi',
                'colour' => '#0284c7',
                'source' => 'user',
            ]);

        $landmark = Landmark::where('name', 'Hydration Station A')->firstOrFail();
        $this->assertSame('user', $landmark->source);
        $this->assertSame('poi', $landmark->category);
        $this->assertFalse($landmark->label_visible);
        $this->assertTrue($landmark->active);

        $this->actingAs($user)->postJson('/api/landmarks', [
            'name' => 'Not A Real Type',
            'marker_type' => 'portal',
            'easting' => 476300,
            'northing' => 7718500,
        ])->assertUnprocessable();

        $this->actingAs($user)->postJson('/api/landmarks', [
            'name' => 'Hydration Station A',
            'marker_type' => 'hydration',
            'easting' => 476310,
            'northing' => 7718510,
        ])->assertUnprocessable();

        $this->actingAs($user)
            ->deleteJson("/api/landmarks/{$landmark->id}")
            ->assertOk()
            ->assertJson(['deleted' => true]);
        $this->assertDatabaseMissing('landmarks', ['id' => $landmark->id]);
    }

    public function test_user_cannot_delete_non_user_landmarks(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $landmark = Landmark::create([
            'name' => 'Manual Marker',
            'category' => 'manual',
            'marker_type' => 'landmark',
            'colour' => '#ff5f1f',
            'easting' => 476300,
            'northing' => 7718500,
            'active' => true,
            'label_visible' => false,
            'rotation_deg' => 0,
            'source' => 'manual',
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/landmarks/{$landmark->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('landmarks', ['id' => $landmark->id]);
    }

    public function test_admin_settings_store_accepts_poi_marker_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/settings/landmarks', [
            'name' => 'Hydration Station B',
            'category' => 'poi',
            'marker_type' => 'hydration',
            'colour' => '#0284c7',
            'easting' => 476300,
            'northing' => 7718500,
            'active' => '1',
            'label_visible' => '0',
            'rotation_deg' => '0',
        ])->assertRedirect();

        $this->assertSame('hydration', Landmark::where('name', 'Hydration Station B')->firstOrFail()->marker_type);
    }
}
