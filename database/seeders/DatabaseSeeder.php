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
            ['Confined Space Entry', '#d9534f', true, false, 'Crew / gas test / standby arrangements', false],
            ['Hot Work', '#f0ad4e', false, false, 'Fire watch / controls', false],
            ['Working at Heights', '#5bc0de', false, false, 'Access / fall protection / rescue considerations', false],
            ['Excavation', '#8a6d3b', false, false, 'Depth / services / controls', false],
            ['Electrical Isolation', '#6f42c1', false, false, 'Isolation / lockout details', false],
            ['Inspection', '#5cb85c', false, false, 'Inspection details', false],
            ['Other', '#777777', false, true, 'Describe the high risk work', true],
        ];

        foreach ($types as $i => [$name, $colour, $default, $note, $prompt, $other]) {
            WorkType::updateOrCreate(['name' => $name], [
                'colour' => $colour,
                'is_default' => $default,
                'requires_note' => $note,
                'notes_prompt' => $prompt,
                'is_other' => $other,
                'sort_order' => $i,
                'active' => true,
            ]);
        }

        $username = env('HWRT_ADMIN_USERNAME', env('CSEM_ADMIN_EMAIL', 'admin@example.com'));
        if (str_contains($username, '@')) {
            $username = strtok($username, '@');
        }

        User::updateOrCreate(
            ['username' => strtolower((string) $username)],
            [
                'name' => env('HWRT_ADMIN_NAME', 'Admin'),
                'email' => env('HWRT_ADMIN_EMAIL') ?: null,
                'password' => env('HWRT_ADMIN_PASSWORD', env('CSEM_ADMIN_PASSWORD', 'changeme')),
                'role' => 'admin',
                'active' => true,
            ],
        );
    }
}
