<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WorkType;
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
        foreach ($types as $i => [$name, $colour, $default, $note]) {
            WorkType::updateOrCreate(['name' => $name], [
                'colour' => $colour, 'is_default' => $default, 'requires_note' => $note, 'sort_order' => $i,
            ]);
        }

        User::updateOrCreate(
            ['email' => env('HRWT_ADMIN_EMAIL', env('CSEM_ADMIN_EMAIL', 'admin@example.com'))],
            ['name' => 'Admin User', 'password' => env('HRWT_ADMIN_PASSWORD', env('CSEM_ADMIN_PASSWORD', 'changeme')), 'role' => 'admin'],
        );

        User::updateOrCreate(
            ['email' => 'supervisor@example.com'],
            ['name' => 'Supervisor User', 'password' => 'changeme', 'role' => 'supervisor'],
        );

        User::updateOrCreate(
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
    }
}
