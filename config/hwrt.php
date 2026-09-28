<?php

return [
    'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: '2.0.0-dev',

    // Age thresholds for open high-risk-work records.
    'amber_hours' => (float) env('HWRT_AMBER_HOURS', env('CSEM_AMBER_HOURS', 2)),
    'red_hours' => (float) env('HWRT_RED_HOURS', env('CSEM_RED_HOURS', 4)),

    // Local, self-contained map package. Runtime operation does not require ArcGIS.
    'sitemap_path' => env(
        'HWRT_SITEMAP_PATH',
        env('CSEM_SITEMAP_PATH', env('APP_ENV') === 'production'
            ? storage_path('app/hwrt-sitemap/current')
            : base_path('sitemap')),
    ),
    'sitemap_url' => env('HWRT_SITEMAP_URL', env('CSEM_SITEMAP_URL', '/sitemap')),

    'duplicate_radius_m' => (float) env('HWRT_DUPLICATE_RADIUS_M', 15),
    'poll_seconds' => (int) env('HWRT_POLL_SECONDS', 15),

    'bootstrap_admin' => [
        'name' => env('HWRT_ADMIN_NAME', 'Admin'),
        'username' => env('HWRT_ADMIN_USERNAME', env('CSEM_ADMIN_EMAIL', 'admin')),
        'email' => env('HWRT_ADMIN_EMAIL') ?: null,
        'password' => env('HWRT_ADMIN_PASSWORD', env('CSEM_ADMIN_PASSWORD', 'admin')),
    ],

    // Runtime privacy target. Frontend libraries are served locally.
    'allow_external_assets' => (bool) env('HWRT_ALLOW_EXTERNAL_ASSETS', false),
];
