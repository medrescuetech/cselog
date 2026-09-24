<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Entry;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $logger;

    private User $viewer;

    private WorkType $type;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = User::factory()->create(['role' => 'logger']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
        $this->type = WorkType::create(['name' => 'Confined Space Entry', 'colour' => '#d9534f', 'sort_order' => 1]);
        $this->area = Area::create([
            'kind' => 'lease', 'name' => 'Site C', 'source' => 'test',
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[476000, 7718000], [477000, 7718000], [477000, 7719000], [476000, 7719000], [476000, 7718000]]]],
        ]);
    }

    public function test_guest_is_redirected_and_viewer_cannot_log(): void
    {
        $this->get('/board')->assertRedirect('/login');
        $this->actingAs($this->viewer)->get('/board')->assertOk();
        $this->actingAs($this->viewer)->get('/log')->assertForbidden();
        $this->actingAs($this->viewer)->post('/log', [])->assertForbidden();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $u = User::factory()->create(['active' => false, 'password' => 'secret123']);
        $this->post('/login', ['email' => $u->email, 'password' => 'secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_adhoc_entry_gets_area_snapshot_and_closes(): void
    {
        $this->actingAs($this->logger)
            ->post('/log', ['work_type_id' => $this->type->id, 'location_label' => 'Pit 7', 'easting' => 476500, 'northing' => 7718500, 'notes' => 'two in'])
            ->assertRedirect('/board');

        $entry = Entry::firstOrFail();
        $this->assertNull($entry->location_id);
        $this->assertSame($this->area->id, $entry->area_id);
        $this->assertSame('open', $entry->status);
        $this->assertCount(1, $entry->events);

        $this->actingAs($this->logger)->post("/entries/{$entry->id}/close", ['close_note' => 'all out'])->assertRedirect();
        $entry->refresh();
        $this->assertSame('closed', $entry->status);
        $this->assertSame($this->logger->id, $entry->closed_by);
        $this->assertSame('closed', $entry->events()->latest('id')->first()->event);

        $this->actingAs($this->logger)->get('/api/open')->assertOk()->assertJsonCount(0, 'entries');
    }

    public function test_catalogue_location_snapshot_is_immutable(): void
    {
        $loc = Location::create(['name' => 'Control Room', 'easting' => 476100, 'northing' => 7718100, 'area_id' => $this->area->id]);
        $this->actingAs($this->logger)->post('/log', ['work_type_id' => $this->type->id, 'location_id' => $loc->id])->assertSessionHasNoErrors();
        $loc->update(['name' => 'Renamed', 'easting' => 400000]);

        $entry = Entry::firstOrFail();
        $this->assertSame('Control Room', $entry->location_label);
        $this->assertEqualsWithDelta(476100, $entry->easting, 0.001);
        $this->assertSame(1, $loc->fresh()->usage_count);
    }

    public function test_nearby_warns_within_radius_and_new_location_is_unverified(): void
    {
        Location::create(['name' => 'Tank 1', 'easting' => 476100, 'northing' => 7718100]);
        $this->actingAs($this->logger)->get('/api/locations/nearby?easting=476110&northing=7718100')
            ->assertOk()->assertJsonCount(1, 'nearby')->assertJsonPath('area.name', 'Site C');
        $this->actingAs($this->logger)->get('/api/locations/nearby?easting=476200&northing=7718100')
            ->assertOk()->assertJsonCount(0, 'nearby');

        $this->actingAs($this->logger)->postJson('/api/locations', ['name' => 'Tank 2', 'easting' => 476300, 'northing' => 7718300])
            ->assertCreated()->assertJsonPath('verified', false)->assertJsonPath('area_id', $this->area->id);
    }

    public function test_working_at_heights_job_logging_and_filtering(): void
    {
        $heightsType = WorkType::create(['name' => 'Working at Heights', 'colour' => '#5bc0de', 'sort_order' => 2]);

        $this->actingAs($this->logger)
            ->post('/log', [
                'work_type_id' => $heightsType->id,
                'location_label' => 'Scaffold Platform 4B',
                'easting' => 476550,
                'northing' => 7718550,
                'notes' => 'Harness and lanyard inspected',
                'permit_no' => 'WAH-102',
            ])
            ->assertRedirect('/board');

        $entry = Entry::where('permit_no', 'WAH-102')->firstOrFail();
        $this->assertSame($heightsType->id, $entry->work_type_id);
        $this->assertSame('Working at Heights', $entry->workType->name);

        $this->actingAs($this->viewer)
            ->get('/history?work_type_id=' . $heightsType->id)
            ->assertOk()
            ->assertSee('Scaffold Platform 4B')
            ->assertSee('WAH-102')
            ->assertSee('Working at Heights');

        $this->actingAs($this->viewer)
            ->get('/board')
            ->assertOk()
            ->assertSee('Scaffold Platform 4B')
            ->assertSee('Working at Heights');
    }

    public function test_history_filters_and_csv(): void
    {
        $this->actingAs($this->logger)->post('/log', ['work_type_id' => $this->type->id, 'location_label' => 'A', 'easting' => 476500, 'northing' => 7718500, 'permit_no' => 'GDP-9']);
        $this->actingAs($this->logger)->post('/log', ['work_type_id' => $this->type->id, 'location_label' => 'B', 'easting' => 1, 'northing' => 1]);

        $this->actingAs($this->viewer)->get('/history?q=GDP-9')->assertOk()->assertSee('A')->assertDontSee('>B<', false);
        $this->actingAs($this->viewer)->get('/history?area_id='.$this->area->id)->assertOk()->assertSee('GDP-9');

        $csv = $this->actingAs($this->viewer)->get('/history.csv?q=GDP-9')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringContainsString('id,opened_at,closed_at', $csv);
        $this->assertStringContainsString('GDP-9', $csv);
        $this->assertStringNotContainsString(',B,', $csv);
    }

    public function test_layers_come_from_manifest_in_mga50(): void
    {
        $r = $this->actingAs($this->viewer)->get('/api/layers')->assertOk();
        $this->assertSame('EPSG:28350', $r->json('crs'));
        $this->assertNotEmpty($r->json('rasters'));
        $this->assertStringStartsWith('/sitemap/', $r->json('rasters.0.url'));
    }
}
