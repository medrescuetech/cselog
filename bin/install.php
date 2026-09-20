<?php

declare(strict_types=1);

/**
 * First-run installer / seeder.
 *
 *   php bin/install.php                 create tables, seed reference data
 *   php bin/install.php --demo          also insert demo locations and entries
 *   php bin/install.php --admin-pass=x  set the admin password (else generated)
 *
 * Safe to re-run: tables use IF NOT EXISTS and seeds are skipped when present.
 */

use CseLog\Config;
use CseLog\Db;
use CseLog\Support;

require __DIR__ . '/../src/bootstrap.php';

$options = getopt('', ['demo', 'admin-pass::', 'admin-user::']);
$withDemo = array_key_exists('demo', $options);
$adminUser = (string) ($options['admin-user'] ?? 'admin');
$adminPass = (string) ($options['admin-pass'] ?? bin2hex(random_bytes(6)));

$envPath = dirname(__DIR__) . '/.env';
if (!file_exists($envPath)) {
    copy(dirname(__DIR__) . '/.env.example', $envPath);
    Config::load($envPath);
    echo "Created .env from .env.example\n";
}

Db::migrate();
echo "Schema applied (" . Db::driver() . ")\n";

$now = Support::nowUtc();

if ((int) Db::value('SELECT COUNT(*) FROM users') === 0) {
    Db::insert('users', [
        'username' => strtolower($adminUser),
        'display_name' => 'Administrator',
        'password_hash' => password_hash($adminPass, PASSWORD_DEFAULT),
        'role' => 'admin',
        'active' => 1,
        'created_at' => $now,
    ]);
    echo "Admin user created: {$adminUser} / {$adminPass}\n";
}

if ((int) Db::value('SELECT COUNT(*) FROM work_types') === 0) {
    $types = [
        ['Confined Space Entry', '#d93a2b', 1, 0],
        ['Hot Work', '#e8871a', 0, 0],
        ['Working at Heights', '#7048c4', 0, 0],
        ['Excavation', '#8a6d3b', 0, 0],
        ['Electrical Isolation', '#2b7fd9', 0, 0],
        ['Inspection', '#1f9d55', 0, 0],
        ['Other', '#6b7785', 0, 1],
    ];
    foreach ($types as $i => [$name, $colour, $isDefault, $requiresNote]) {
        Db::insert('work_types', [
            'name' => $name,
            'colour' => $colour,
            'is_default' => $isDefault,
            'requires_note' => $requiresNote,
            'sort_order' => $i,
            'active' => 1,
        ]);
    }
    echo "Seeded " . count($types) . " work types\n";
}

$siteId = (int) (Db::value('SELECT id FROM sites ORDER BY id LIMIT 1') ?? 0);
if ($siteId === 0) {
    $siteId = Db::insert('sites', [
        'name' => Config::get('SITE_NAME', 'Main Site') ?? 'Main Site',
        'timezone' => Support::timezone(),
        'active' => 1,
        'created_at' => $now,
    ]);
    echo "Created site #{$siteId}\n";
}

$mapId = (int) (Db::value('SELECT id FROM maps ORDER BY id LIMIT 1') ?? 0);
if ($mapId === 0) {
    $mapId = Db::insert('maps', [
        'site_id' => $siteId,
        'name' => 'Site plan (placeholder)',
        'image_path' => '/maps/placeholder-site.svg',
        'width_px' => 2000,
        'height_px' => 1400,
        'crs' => 'simple',
        'is_default' => 1,
        'active' => 1,
        'created_at' => $now,
    ]);
    echo "Created map #{$mapId} using the placeholder site plan\n";
}

if ((int) Db::value('SELECT COUNT(*) FROM areas') === 0) {
    $areas = [
        ['Tank Farm', 'zone', '#d93a2b', [[820, 140], [1540, 140], [1540, 580], [820, 580]]],
        ['Process Plant', 'zone', '#7048c4', [[180, 160], [600, 160], [600, 580], [180, 580]]],
        ['Pond Area', 'zone', '#2b7fd9', [[150, 860], [610, 860], [610, 1180], [150, 1180]]],
        ['Laydown Yard', 'zone', '#1f9d55', [[800, 820], [1320, 820], [1320, 1200], [800, 1200]]],
        ['Workshop & Admin', 'zone', '#e8871a', [[1500, 800], [1880, 800], [1880, 1260], [1500, 1260]]],
    ];
    foreach ($areas as [$name, $kind, $colour, $ring]) {
        Db::insert('areas', [
            'map_id' => $mapId,
            'name' => $name,
            'kind' => $kind,
            'geometry_px' => json_encode([$ring]),
            'colour' => $colour,
            'active' => 1,
            'created_at' => $now,
        ]);
    }
    echo "Seeded " . count($areas) . " areas\n";
}

if ((int) Db::value('SELECT COUNT(*) FROM landmarks') === 0) {
    $landmarks = [
        ['Gate 1', 'gate', 155, 700],
        ['Weighbridge', 'infrastructure', 390, 670],
        ['Muster Point A', 'muster', 1720, 620],
        ['Control Room', 'infrastructure', 1690, 1170],
        ['Workshop', 'infrastructure', 1690, 940],
    ];
    foreach ($landmarks as [$name, $category, $x, $y]) {
        Db::insert('landmarks', [
            'map_id' => $mapId,
            'name' => $name,
            'category' => $category,
            'x' => $x,
            'y' => $y,
            'colour' => $category === 'muster' ? '#1f9d55' : '#5a6b78',
            'active' => 1,
            'created_at' => $now,
        ]);
    }
    echo "Seeded " . count($landmarks) . " landmarks\n";
}

if ($withDemo && (int) Db::value('SELECT COUNT(*) FROM locations') === 0) {
    $userId = (int) Db::value('SELECT id FROM users ORDER BY id LIMIT 1');
    $areaByName = [];
    foreach (Db::all('SELECT id, name FROM areas') as $area) {
        $areaByName[$area['name']] = (int) $area['id'];
    }

    $locations = [
        ['Vessel T-301', 'Tank Farm', 960, 330],
        ['Vessel T-302', 'Tank Farm', 1180, 330],
        ['Bund sump, tank farm east', 'Tank Farm', 1460, 505],
        ['Sump 4 – north wall', 'Pond Area', 240, 880],
        ['Pond inlet chamber', 'Pond Area', 380, 880],
        ['Silo 2 manway', 'Process Plant', 490, 310],
        ['Pump house wet well', 'Process Plant', 395, 475],
        ['Pit 12 access', 'Laydown Yard', 1180, 1080],
        ['Stores roof void', 'Laydown Yard', 940, 960],
        ['Workshop inspection pit', 'Workshop & Admin', 1690, 900],
    ];
    foreach ($locations as $i => [$name, $areaName, $x, $y]) {
        Db::insert('locations', [
            'site_id' => $siteId,
            'map_id' => $mapId,
            'name' => $name,
            'area_id' => $areaByName[$areaName] ?? null,
            'x' => $x,
            'y' => $y,
            'status' => 'active',
            'verified' => $i < 6 ? 1 : 0,
            'usage_count' => max(0, 12 - $i),
            'last_used_at' => gmdate('Y-m-d H:i:s', time() - ($i * 7200)),
            'created_by' => $userId,
            'created_at' => $now,
        ]);
    }
    echo "Seeded " . count($locations) . " demo locations\n";

    $cse = (int) Db::value("SELECT id FROM work_types WHERE name = 'Confined Space Entry'");
    $other = (int) Db::value("SELECT id FROM work_types WHERE name = 'Other'");
    $hot = (int) Db::value("SELECT id FROM work_types WHERE name = 'Hot Work'");

    $demoEntries = [
        [1, $cse, '2 crew in, Jacobs. Gas tested 09:10.', 'Ch.2 – Dave', 4.2, null],
        [4, $cse, 'Cleaning out silt. Attendant on top.', 'Ch.1 – Sam', 2.8, null],
        [8, $cse, 'Cable pull, 1 crew.', 'Ch.2 – Dave', 0.9, null],
        [10, $other, 'Drain inspection, no entry required yet.', 'Ch.3 – Kel', 0.4, null],
        [6, $cse, 'Manway open for inspection.', 'Ch.1 – Sam', 0.2, null],
        [2, $cse, 'Internal inspection, completed.', 'Ch.2 – Dave', 26.0, 22.0],
        [5, $hot, 'Welding repair on inlet frame.', 'Ch.4 – Trev', 30.0, 27.5],
        [9, $cse, 'Roof void cable run.', 'Ch.1 – Sam', 52.0, 48.0],
    ];
    $locIds = array_column(Db::all('SELECT id FROM locations ORDER BY id'), 'id');
    foreach ($demoEntries as [$locIndex, $typeId, $notes, $reportedBy, $openedHoursAgo, $closedHoursAgo]) {
        $locationId = (int) $locIds[$locIndex - 1];
        $location = Db::first('SELECT * FROM locations WHERE id = ?', [$locationId]);
        $openedAt = gmdate('Y-m-d H:i:s', time() - (int) ($openedHoursAgo * 3600));
        $entryId = Db::insert('entries', [
            'site_id' => $siteId,
            'map_id' => $mapId,
            'location_id' => $locationId,
            'location_label' => $location['name'],
            'x' => $location['x'],
            'y' => $location['y'],
            'area_id' => $location['area_id'],
            'work_type_id' => $typeId,
            'notes' => $notes,
            'reported_by' => $reportedBy,
            'status' => $closedHoursAgo === null ? 'open' : 'closed',
            'opened_at' => $openedAt,
            'opened_by' => $userId,
            'closed_at' => $closedHoursAgo === null ? null : gmdate('Y-m-d H:i:s', time() - (int) ($closedHoursAgo * 3600)),
            'closed_by' => $closedHoursAgo === null ? null : $userId,
            'created_at' => $openedAt,
        ]);
        Db::insert('entry_events', [
            'entry_id' => $entryId,
            'event' => 'created',
            'actor_id' => $userId,
            'changes' => json_encode(['seeded' => true]),
            'occurred_at' => $openedAt,
        ]);
    }
    echo "Seeded " . count($demoEntries) . " demo entries\n";
}

echo "Done.\n";
