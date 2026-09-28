<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LocationAndCustomReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_pre_store_move_and_archive_location_without_rewriting_job_snapshots(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'user']);
        $type = WorkType::create(['name' => 'Excavation', 'colour' => '#123456', 'active' => true]);
        $payload = ['name' => 'Old tank', 'code' => 'T-1', 'aliases_text' => 'Tank One, North vessel', 'easting' => 476000, 'northing' => 7718000];

        $this->actingAs($operator)->post('/settings/locations', $payload)->assertForbidden();
        $this->actingAs($admin)->post('/settings/locations', $payload)->assertRedirect();
        $location = Location::where('name', 'Old tank')->firstOrFail();
        $this->assertTrue($location->verified);
        $this->assertSame(['Tank One', 'North vessel'], $location->aliases);
        $this->actingAs($operator)->get('/api/locations?q=North%20vessel')->assertJsonPath('0.name', 'Old tank');

        $this->actingAs($operator)->post('/log', ['work_type_id' => $type->id, 'location_id' => $location->id])->assertRedirect();
        $entry = Entry::firstOrFail();
        $this->actingAs($admin)->patch("/settings/locations/{$location->id}", $payload + ['reason' => 'Survey correction'])->assertRedirect();
        $this->actingAs($admin)->patch("/settings/locations/{$location->id}", array_merge($payload, [
            'name' => 'New tank', 'easting' => 476050, 'reason' => 'Survey correction',
        ]))->assertRedirect();
        $this->assertSame('Old tank', $entry->fresh()->location_label);
        $this->assertEqualsWithDelta(476000, $entry->fresh()->easting, 0.001);
        $this->assertSame(476050.0, $location->fresh()->easting);
        $this->assertSame('moved', $location->revisions()->first()->action);

        $this->actingAs($admin)->patch("/settings/locations/{$location->id}/status", [
            'status' => 'archived', 'reason' => 'Removed from site',
        ])->assertRedirect();
        $this->actingAs($operator)->get('/api/locations?q=New%20tank')->assertJsonCount(0);
        $this->actingAs($operator)->post('/log', ['work_type_id' => $type->id, 'location_id' => $location->id])
            ->assertSessionHasErrors('location_id');
        $this->actingAs($operator)->get(route('reports.custom', ['location_id' => $location->id]))
            ->assertOk()->assertSee('Old tank');
    }

    public function test_custom_report_matches_filters_columns_groups_and_csv_and_escapes_formula_text(): void
    {
        $operator = User::factory()->create(['role' => 'user']);
        $other = WorkType::create(['name' => 'Other', 'colour' => '#123456', 'active' => true, 'is_other' => true]);
        $location = Location::create(['name' => 'Wharf', 'easting' => 476000, 'northing' => 7718000]);
        Entry::create([
            'location_id' => $location->id, 'location_label' => 'Wharf', 'easting' => 476000,
            'northing' => 7718000, 'work_type_id' => $other->id, 'other_description' => 'Pressure test',
            'notes' => '=SUM(1,1)', 'status' => 'closed', 'opened_at' => '2026-09-27 09:00:00',
            'closed_at' => '2026-09-27 10:00:00', 'opened_by' => $operator->id,
        ]);
        Entry::create([
            'location_label' => 'Other pit', 'easting' => 476001, 'northing' => 7718001,
            'work_type_id' => $other->id, 'other_description' => 'Other job', 'status' => 'open',
            'opened_at' => '2026-09-27 09:00:00', 'opened_by' => $operator->id,
        ]);
        $filters = ['from' => '2026-09-27', 'to' => '2026-09-27', 'work_type_id' => $other->id,
            'location_id' => $location->id, 'group_by' => 'other_type',
            'columns' => ['hrw_id', 'location', 'other_type', 'notes']];
        $this->actingAs($operator)->get(route('reports.custom', $filters))->assertOk()
            ->assertSee('1 matching job(s)')->assertSee('Pressure test')->assertDontSee('Other pit');
        $csv = $this->actingAs($operator)->get(route('reports.custom.csv', $filters))->assertOk()->streamedContent();
        $this->assertStringContainsString('Location at logging', $csv);
        $this->assertStringContainsString('Other description', $csv);
        $this->assertStringContainsString('Pressure test', $csv);
        $this->assertStringContainsString("'=SUM(1,1)", $csv);
        $this->assertStringNotContainsString('Other pit', $csv);
    }

    public function test_handover_shows_open_and_upcoming_jobs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 17:30:00', 'Australia/Perth'));
        $operator = User::factory()->create(['role' => 'user']);
        $type = WorkType::create(['name' => 'Hot Work', 'colour' => '#123456', 'active' => true]);
        foreach (['open', 'pending'] as $status) {
            Entry::create([
                'location_label' => $status === 'open' ? 'Live tank' : 'Future tank',
                'easting' => 476000, 'northing' => 7718000, 'work_type_id' => $type->id,
                'status' => $status, 'opened_at' => $status === 'open' ? '2026-09-28 12:00:00' : '2026-09-28 18:00:00',
                'planned_start_at' => $status === 'pending' ? '2026-09-28 18:00:00' : null,
                'opened_by' => $operator->id,
            ]);
        }
        $this->actingAs($operator)->get('/reports/handover')->assertOk()
            ->assertSee('Open at handover (1)')->assertSee('Pending next 12 hours (1)')
            ->assertSee('Live tank')->assertSee('Future tank');
        Carbon::setTestNow();
    }
}
