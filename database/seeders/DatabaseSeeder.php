<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WorkType;
use App\Models\Entry;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['Confined Space Entry', '#d9534f', true, false],
            ['Hot Work', '#f0ad4e', false, false],
            ['Working at Heights', '#5bc0de', false, false],
            ['Excavation', '#8a6d3b', false, false],
            ['Electrical Isolation', '#6f42c1', false, false],
            ['Inspection', '#5cb85c', false, false],
            ['Other', '#777777', false, true],
        ];
        $workTypes = [];
        foreach ($types as $i => [$name, $colour, $default, $note]) {
            $workTypes[$name] = WorkType::updateOrCreate(['name' => $name], [
                'colour' => $colour, 'is_default' => $default, 'requires_note' => $note, 'sort_order' => $i,
            ]);
        }

        $admin = User::updateOrCreate(
            ['email' => env('HRWT_ADMIN_EMAIL', env('CSEM_ADMIN_EMAIL', 'admin@example.com'))],
            ['name' => 'Admin User', 'password' => env('HRWT_ADMIN_PASSWORD', env('CSEM_ADMIN_PASSWORD', 'changeme')), 'role' => 'admin'],
        );

        $supervisor = User::updateOrCreate(
            ['email' => 'supervisor@example.com'],
            ['name' => 'Supervisor User', 'password' => 'changeme', 'role' => 'supervisor'],
        );

        $logger = User::updateOrCreate(
            ['email' => 'logger@example.com'],
            ['name' => 'Normal Logger User', 'password' => 'changeme', 'role' => 'logger'],
        );

        User::updateOrCreate(
            ['email' => 'viewer@example.com'],
            ['name' => 'Read Only Viewer', 'password' => 'changeme', 'role' => 'viewer'],
        );

        User::updateOrCreate(
            ['email' => 'maponly@example.com'],
            ['name' => 'Map Only User', 'password' => 'changeme', 'role' => 'map_only'],
        );

        // Seed sample open entries for map display (within site map extent ~476000 E, 7718000 N)
        if (Entry::count() === 0) {
            Entry::create([
                'work_type_id' => $workTypes['Working at Heights']->id,
                'location_label' => 'North Ridge Substation',
                'easting' => 476500.0,
                'northing' => 7718500.0,
                'opened_at' => now()->subMinutes(45),
                'opened_by' => $logger->id,
                'permit_no' => 'PERMIT-2026-101',
                'notes' => 'Inspecting transformer insulators at 12m height',
                'status' => 'open',
            ]);

            Entry::create([
                'work_type_id' => $workTypes['Confined Space Entry']->id,
                'location_label' => 'South Pit Chamber 2B',
                'easting' => 476280.0,
                'northing' => 7718050.0,
                'opened_at' => now()->subMinutes(20),
                'opened_by' => $supervisor->id,
                'permit_no' => 'CSE-2026-089',
                'notes' => 'Gas testing and valve replacement',
                'status' => 'open',
            ]);
        }
    }
}
