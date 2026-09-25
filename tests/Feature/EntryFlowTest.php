<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Entry;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EntryFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $admin;
    private WorkType $type;
    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Australia/Perth']);

        $this->user = User::factory()->create(['role' => 'user']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->type = WorkType::create([
            'name' => 'Confined Space Entry',
            'colour' => '#d9534f',
            'sort_order' => 1,
            'active' => true,
        ]);
        $this->area = Area::create([
            'kind' => 'lease',
            'name' => 'Site C',
            'source' => 'test',
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[[476000, 7718000], [477000, 7718000], [477000, 7719000], [476000, 7719000], [476000, 7718000]]],
            ],
        ]);
    }

    public function test_guest_is_redirected_and_user_can_operate_but_not_open_settings(): void
    {
        $this->get('/board')->assertRedirect('/login');
        $this->actingAs($this->user)->get('/board')->assertOk();
        $this->actingAs($this->user)->get('/log')->assertOk();
        $this->actingAs($this->user)->get('/settings')->assertForbidden();
        $this->actingAs($this->admin)->get('/settings')->assertOk();
    }

    public function test_open_board_has_explicit_table_headers_and_hrw_id(): void
    {
        $this->actingAs($this->user)
            ->get('/board')
            ->assertOk()
            ->assertSee('HRW ID')
            ->assertSee('Elapsed')
            ->assertSee('Type')
            ->assertSee('Location')
            ->assertSee('Area')
            ->assertSee('Permit')
            ->assertSee('Notes / Reported by')
            ->assertSee('Opened')
            ->assertSee('Opened by');
    }

    public function test_inactive_user_cannot_login_by_username(): void
    {
        $u = User::factory()->create([
            'active' => false,
            'username' => 'inactiveuser',
            'password' => 'secret123456',
        ]);

        $this->post('/login', [
            'login' => 'inactiveuser',
            'password' => 'secret123456',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_username_login_works_without_email(): void
    {
        $u = User::factory()->create([
            'username' => 'controlroom',
            'email' => null,
            'password' => 'secret123456',
        ]);

        $this->post('/login', [
            'login' => 'controlroom',
            'password' => 'secret123456',
        ])->assertRedirect('/board');

        $this->assertAuthenticatedAs($u);
    }

    public function test_adhoc_entry_gets_hrw_reference_area_snapshot_and_closes(): void
    {
        $this->actingAs($this->user)
            ->post('/log', [
                'work_type_id' => $this->type->id,
                'location_label' => 'Pit 7',
                'easting' => 476500,
                'northing' => 7718500,
                'notes' => 'two in',
            ])
            ->assertRedirect('/board');

        $entry = Entry::firstOrFail();
        $this->assertSame('HRW-000001', $entry->hrw_ref);
        $this->assertNull($entry->location_id);
        $this->assertSame($this->area->id, $entry->area_id);
        $this->assertSame('open', $entry->status);
        $this->assertCount(1, $entry->events);

        $this->actingAs($this->user)
            ->post("/entries/{$entry->id}/close", ['close_note' => 'all out'])
            ->assertRedirect();

        $entry->refresh();
        $this->assertSame('closed', $entry->status);
        $this->assertSame($this->user->id, $entry->closed_by);
        $this->assertSame('closed', $entry->events()->latest('id')->first()->event);

        $this->actingAs($this->user)->get('/api/open')->assertOk()->assertJsonCount(0, 'entries');
    }

    public function test_future_work_is_pending_and_appears_on_board_on_planned_perth_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-25 08:00:00', 'Australia/Perth'));

        $this->actingAs($this->user)->post('/log', [
            'work_type_id' => $this->type->id,
            'location_label' => 'Planned vessel',
            'easting' => 476500,
            'northing' => 7718500,
            'planned_start_at' => '2026-09-25T14:30',
        ])->assertRedirect('/pending');

        $entry = Entry::firstOrFail();
        $this->assertSame('pending', $entry->status);
        $this->assertSame('2026-09-25 14:30', $entry->planned_start_at->format('Y-m-d H:i'));

        $this->actingAs($this->user)
            ->get('/api/open')
            ->assertOk()
            ->assertJsonCount(0, 'entries')
            ->assertJsonCount(1, 'pending_today')
            ->assertJsonPath('pending_today.0.hrw_ref', $entry->hrw_ref);

        Carbon::setTestNow(Carbon::parse('2026-09-25 14:00:00', 'Australia/Perth'));

        $this->actingAs($this->user)
            ->post("/entries/{$entry->id}/start")
            ->assertRedirect('/board');

        $entry->refresh();
        $this->assertSame('open', $entry->status);
        $this->assertSame('2026-09-25 14:00', $entry->opened_at->format('Y-m-d H:i'));
        $this->assertSame('2026-09-25 14:30', $entry->planned_start_at->format('Y-m-d H:i'));

        Carbon::setTestNow();
    }

    public function test_catalogue_location_snapshot_is_immutable(): void
    {
        $loc = Location::create([
            'name' => 'Control Room',
            'easting' => 476100,
            'northing' => 7718100,
            'area_id' => $this->area->id,
        ]);

        $this->actingAs($this->user)
            ->post('/log', ['work_type_id' => $this->type->id, 'location_id' => $loc->id])
            ->assertSessionHasNoErrors();

        $loc->update(['name' => 'Renamed', 'easting' => 400000]);

        $entry = Entry::firstOrFail();
        $this->assertSame('Control Room', $entry->location_label);
        $this->assertEqualsWithDelta(476100, $entry->easting, 0.001);
        $this->assertSame(1, $loc->fresh()->usage_count);
    }

    public function test_nearby_warns_and_new_location_is_unverified(): void
    {
        Location::create(['name' => 'Tank 1', 'easting' => 476100, 'northing' => 7718100]);

        $this->actingAs($this->user)
            ->get('/api/locations/nearby?easting=476110&northing=7718100')
            ->assertOk()
            ->assertJsonCount(1, 'nearby')
            ->assertJsonPath('area.name', 'Site C');

        $this->actingAs($this->user)
            ->postJson('/api/locations', ['name' => 'Tank 2', 'easting' => 476300, 'northing' => 7718300])
            ->assertCreated()
            ->assertJsonPath('verified', false)
            ->assertJsonPath('area_id', $this->area->id);
    }

    public function test_history_filters_and_csv_include_hrw_id(): void
    {
        $this->actingAs($this->user)->post('/log', [
            'work_type_id' => $this->type->id,
            'location_label' => 'A',
            'easting' => 476500,
            'northing' => 7718500,
            'permit_no' => 'GDP-9',
        ]);

        $this->actingAs($this->user)->post('/log', [
            'work_type_id' => $this->type->id,
            'location_label' => 'B',
            'easting' => 1,
            'northing' => 1,
        ]);

        $this->actingAs($this->user)->get('/history?q=GDP-9')->assertOk()->assertSee('A');
        $this->actingAs($this->user)->get('/history?area_id='.$this->area->id)->assertOk()->assertSee('GDP-9');

        $csv = $this->actingAs($this->user)
            ->get('/history.csv?q=GDP-9')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString('hrw_id,id,planned_start_at,opened_at', $csv);
        $this->assertStringContainsString('HRW-000001', $csv);
        $this->assertStringContainsString('GDP-9', $csv);
    }

    public function test_layers_are_local_and_do_not_expose_arcgis_source_urls(): void
    {
        $r = $this->actingAs($this->user)->get('/api/layers')->assertOk();
        $this->assertSame('EPSG:28350', $r->json('crs'));
        $this->assertNotEmpty($r->json('rasters'));
        $this->assertStringStartsWith('/sitemap/', $r->json('rasters.0.url'));
        $this->assertArrayNotHasKey('source', $r->json('rasters.0'));
    }
}
