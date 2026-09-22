<?php

return [
    // Age thresholds for open entries (hours). docs/11: default 2 h amber / 4 h red.
    'amber_hours' => (float) env('CSEM_AMBER_HOURS', 2),
    'red_hours' => (float) env('CSEM_RED_HOURS', 4),

    // Standalone map package (see sitemap/README.md). Served at /sitemap via public/sitemap symlink.
    'sitemap_path' => env('CSEM_SITEMAP_PATH', base_path('sitemap')),
    'sitemap_url' => env('CSEM_SITEMAP_URL', '/sitemap'),

    // Warn when a new pin is within this many metres of an existing location.
    'duplicate_radius_m' => 15,

    // Board refresh interval (seconds).
    'poll_seconds' => 15,
];
