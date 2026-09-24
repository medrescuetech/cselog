<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Entry;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LocationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;
    private User $logger;
    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->logger = User::factory()->create(['role' => 'logger']);
        $this->area = Area::create([
            'kind' => 'lease', 'name' => 'Site F', 'source' => 'test',
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[476000, 7718000], [477000, 7718000], [477000, 7719000], [476000, 7719000], [476000, 7718000]]]],
        ]);
    }

    public function test_logger_cannot_access_location_admin(): void
    {
        $this->actingAs($this->logger)->get('/admin/locations')->assertForbidden();
    }

    public function test_supervisor_can_verify_and_archive_locations(): void
    {
        $loc = Location::create(['name' => 'Unverified Pit', 'easting' => 476100, 'northing' => 7718100, 'verified' => false]);

        $this->actingAs($this->supervisor)
            ->post("/admin/locations/{$loc->id}/verify")
            ->assertRedirect();
        $this->assertTrue($loc->fresh()->verified);

        $this->actingAs($this->supervisor)
            ->post("/admin/locations/{$loc->id}/archive")
            ->assertRedirect();
        $this->assertSame('archived', $loc->fresh()->status);
    }

    public function test_supervisor_can_merge_duplicate_locations(): void
    {
        $type = WorkType::create(['name' => 'Confined Space Entry', 'colour' => '#d9534f', 'sort_order' => 1]);
        $target = Location::create(['name' => 'Sump 4', 'easting' => 476100, 'northing' => 7718100, 'usage_count' => 5]);
        $duplicate = Location::create(['name' => 'Sump 4 (North)', 'easting' => 476105, 'northing' => 7718105, 'usage_count' => 3]);

        $entry = Entry::create([
            'location_id' => $duplicate->id,
            'location_label' => $duplicate->name,
            'easting' => 476105,
            'northing' => 7718105,
            'work_type_id' => $type->id,
            'status' => 'open',
            'opened_at' => now(),
            'opened_by' => $this->logger->id,
        ]);

        $this->actingAs($this->supervisor)
            ->post("/admin/locations/{$duplicate->id}/merge", ['target_id' => $target->id])
            ->assertRedirect();

        $this->assertSame($target->id, $entry->fresh()->location_id);
        $this->assertSame('archived', $duplicate->fresh()->status);
        $this->assertSame($target->id, $duplicate->fresh()->merged_into_id);
        $this->assertSame(8, $target->fresh()->usage_count);
    }

    public function test_supervisor_can_export_and_import_locations_csv(): void
    {
        Location::create(['name' => 'Tank 101', 'code' => 'T101', 'easting' => 476100, 'northing' => 7718100, 'verified' => true]);

        $csv = $this->actingAs($this->supervisor)->get('/admin/locations/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString('Tank 101', $csv);
        $this->assertStringContainsString('T101', $csv);

        $fileContent = "id,name,code,easting,northing\n,Imported Well A,WA-1,476200,7718200\n";
        $upload = UploadedFile::fake()->createWithContent('import.csv', $fileContent);

        $this->actingAs($this->supervisor)
            ->post('/admin/locations/import', ['file' => $upload])
            ->assertRedirect();

        $imported = Location::where('name', 'Imported Well A')->firstOrFail();
        $this->assertSame('WA-1', $imported->code);
        $this->assertTrue($imported->verified);
    }
}
