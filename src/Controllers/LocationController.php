<?php

declare(strict_types=1);

namespace CseLog\Controllers;

use CseLog\Auth;
use CseLog\Config;
use CseLog\Db;
use CseLog\Http;
use CseLog\Repo;
use CseLog\Support;

final class LocationController
{
    /** Type-ahead for the log form: recent and most-used first. */
    public function search(): void
    {
        $site = Repo::site();
        $query = (string) Http::input('q', '');
        $rows = Repo::locationSuggestions((int) $site['id'], $query, 20);

        Http::json(array_map(static fn (array $l): array => [
            'id' => (int) $l['id'],
            'name' => $l['name'],
            'area' => $l['area_name'],
            'verified' => (bool) $l['verified'],
            'usage_count' => (int) $l['usage_count'],
            'x' => (float) $l['x'],
            'y' => (float) $l['y'],
        ], $rows));
    }

    /**
     * Duplicate check used by the pin screen before "save this location?".
     * Warns on a nearby pin or a similar name — the main defence against a
     * catalogue full of near-duplicates.
     */
    public function nearby(): void
    {
        $site = Repo::site();
        $map = Repo::map(Http::intInput('map_id'));
        $x = Http::floatInput('x');
        $y = Http::floatInput('y');
        $name = (string) Http::input('name', '');
        $radius = (float) Config::int('DUPLICATE_RADIUS_PX', 60);

        $matches = [];
        foreach (Repo::locationSuggestions((int) $site['id'], '', 500) as $location) {
            if ((int) $location['map_id'] !== (int) $map['id']) {
                continue;
            }

            $distance = $x === null || $y === null
                ? null
                : Support::distance($x, $y, (float) $location['x'], (float) $location['y']);
            $similarity = $name === '' ? 0.0 : Support::nameSimilarity($name, (string) $location['name']);

            if (($distance !== null && $distance <= $radius) || $similarity >= 0.82) {
                $matches[] = [
                    'id' => (int) $location['id'],
                    'name' => $location['name'],
                    'area' => $location['area_name'],
                    'usage_count' => (int) $location['usage_count'],
                    'distance_px' => $distance === null ? null : round($distance),
                    'similarity' => round($similarity, 2),
                ];
            }
        }

        usort($matches, static fn (array $a, array $b): int => ($a['distance_px'] ?? 1e9) <=> ($b['distance_px'] ?? 1e9));

        Http::json(array_slice($matches, 0, 5));
    }

    /** Admin list with the catalogue-health counters. */
    public function index(): void
    {
        $site = Repo::site();
        $filter = (string) Http::input('filter', 'active');

        $where = 'l.site_id = ?';
        $params = [(int) $site['id']];

        if ($filter === 'unverified') {
            $where .= " AND l.verified = 0 AND l.status = 'active'";
        } elseif ($filter === 'archived') {
            $where .= " AND l.status = 'archived'";
        } elseif ($filter === 'unused') {
            $where .= " AND l.usage_count = 0 AND l.status = 'active'";
        } else {
            $where .= " AND l.status = 'active'";
        }

        $search = (string) Http::input('q', '');
        if ($search !== '') {
            $where .= ' AND LOWER(l.name) LIKE ?';
            $params[] = '%' . strtolower($search) . '%';
        }

        Http::render('admin/locations', [
            'title' => 'Locations',
            'site' => $site,
            'filter' => $filter,
            'search' => $search,
            'locations' => Db::all(
                "SELECT l.*, a.name AS area_name,
                        (SELECT COUNT(*) FROM entries e WHERE e.location_id = l.id) AS entry_count
                   FROM locations l
                   LEFT JOIN areas a ON a.id = l.area_id
                  WHERE $where
                  ORDER BY l.last_used_at DESC, l.name",
                $params
            ),
            'counts' => [
                'active' => (int) Db::value("SELECT COUNT(*) FROM locations WHERE site_id = ? AND status = 'active'", [(int) $site['id']]),
                'unverified' => (int) Db::value("SELECT COUNT(*) FROM locations WHERE site_id = ? AND status = 'active' AND verified = 0", [(int) $site['id']]),
                'unused' => (int) Db::value("SELECT COUNT(*) FROM locations WHERE site_id = ? AND status = 'active' AND usage_count = 0", [(int) $site['id']]),
                'archived' => (int) Db::value("SELECT COUNT(*) FROM locations WHERE site_id = ? AND status = 'archived'", [(int) $site['id']]),
            ],
            'allLocations' => Db::all(
                "SELECT id, name FROM locations WHERE site_id = ? AND status = 'active' ORDER BY name",
                [(int) $site['id']]
            ),
        ]);
    }

    public function update(array $params): void
    {
        $location = $this->find((int) $params['id']);
        $name = trim((string) Http::input('name', (string) $location['name']));

        if ($name === '') {
            Http::flash('A location needs a name.', 'error');
            Http::redirect('/admin/locations');
        }

        // Renaming keeps the old name searchable so radio callers are never wrong.
        if (strtolower($name) !== strtolower((string) $location['name'])) {
            Db::insert('location_aliases', ['location_id' => (int) $location['id'], 'alias' => (string) $location['name']]);
        }

        Db::update('locations', [
            'name' => $name,
            'code' => Http::input('code'),
            'verified' => Http::input('verified') === '1' ? 1 : 0,
            'updated_at' => Support::nowUtc(),
        ], ['id' => (int) $location['id']]);

        Http::flash('Location updated.');
        Http::redirect('/admin/locations');
    }

    public function verify(array $params): void
    {
        $location = $this->find((int) $params['id']);
        Db::update('locations', ['verified' => 1, 'updated_at' => Support::nowUtc()], ['id' => (int) $location['id']]);

        Http::flash('Verified: ' . $location['name']);
        Http::redirect('/admin/locations?filter=' . Http::input('filter', 'unverified'));
    }

    public function archive(array $params): void
    {
        $location = $this->find((int) $params['id']);
        $status = $location['status'] === 'archived' ? 'active' : 'archived';
        Db::update('locations', ['status' => $status, 'updated_at' => Support::nowUtc()], ['id' => (int) $location['id']]);

        Http::flash($status === 'archived' ? 'Archived (history kept).' : 'Restored to the picker.');
        Http::redirect('/admin/locations?filter=' . Http::input('filter', 'active'));
    }

    public function addAlias(array $params): void
    {
        $location = $this->find((int) $params['id']);
        $alias = trim((string) Http::input('alias', ''));

        if ($alias !== '') {
            Db::insert('location_aliases', ['location_id' => (int) $location['id'], 'alias' => $alias]);
            Http::flash('Alias added: ' . $alias);
        }

        Http::redirect('/admin/locations');
    }

    /** Folds a duplicate into a survivor, keeping its name as an alias. */
    public function merge(array $params): void
    {
        $source = $this->find((int) $params['id']);
        $targetId = Http::intInput('target_id');
        $target = $targetId === null ? null : Db::first('SELECT * FROM locations WHERE id = ?', [$targetId]);

        if ($target === null || (int) $target['id'] === (int) $source['id']) {
            Http::flash('Choose a different location to merge into.', 'error');
            Http::redirect('/admin/locations');
        }

        Db::transaction(static function () use ($source, $target): void {
            Db::run('UPDATE entries SET location_id = ? WHERE location_id = ?', [(int) $target['id'], (int) $source['id']]);
            Db::run('UPDATE location_aliases SET location_id = ? WHERE location_id = ?', [(int) $target['id'], (int) $source['id']]);
            Db::insert('location_aliases', ['location_id' => (int) $target['id'], 'alias' => (string) $source['name']]);
            Db::update('locations', [
                'status' => 'archived',
                'merged_into_id' => (int) $target['id'],
                'updated_at' => Support::nowUtc(),
            ], ['id' => (int) $source['id']]);
            Db::run(
                'UPDATE locations SET usage_count = usage_count + ? WHERE id = ?',
                [(int) $source['usage_count'], (int) $target['id']]
            );
        });

        Http::flash('Merged "' . $source['name'] . '" into "' . $target['name'] . '".');
        Http::redirect('/admin/locations');
    }

    /** Moves the catalogue pin (does not touch historical entry pins). */
    public function move(array $params): void
    {
        $location = $this->find((int) $params['id']);
        $x = Http::floatInput('x');
        $y = Http::floatInput('y');

        if ($x === null || $y === null) {
            Http::json(['ok' => false, 'error' => 'Missing coordinates'], 422);
        }

        Db::update('locations', [
            'x' => $x,
            'y' => $y,
            'area_id' => Repo::areaAt((int) $location['map_id'], $x, $y),
            'updated_at' => Support::nowUtc(),
        ], ['id' => (int) $location['id']]);

        Http::json(['ok' => true]);
    }

    /** Promotes an ad-hoc entry pin into the catalogue after the fact. */
    public function promote(array $params): void
    {
        $entry = Db::first('SELECT * FROM entries WHERE id = ?', [(int) $params['id']]);
        $name = trim((string) Http::input('name', ''));

        if ($entry === null || $name === '' || $entry['x'] === null) {
            Http::flash('Cannot save that pin as a location.', 'error');
            Http::redirect('/history');
        }

        $locationId = Db::insert('locations', [
            'site_id' => (int) $entry['site_id'],
            'map_id' => (int) $entry['map_id'],
            'name' => $name,
            'area_id' => $entry['area_id'],
            'x' => (float) $entry['x'],
            'y' => (float) $entry['y'],
            'status' => 'active',
            'verified' => 0,
            'usage_count' => 1,
            'last_used_at' => Support::nowUtc(),
            'created_by' => Auth::id(),
            'created_at' => Support::nowUtc(),
        ]);

        Db::update('entries', ['location_id' => $locationId, 'location_label' => $name], ['id' => (int) $entry['id']]);
        Repo::logEvent((int) $entry['id'], 'updated', ['promoted_to_location' => $name]);

        Http::flash('"' . $name . '" added to the location list.');
        Http::redirect('/entries/' . $entry['id']);
    }

    public function exportCsv(): void
    {
        $site = Repo::site();
        $rows = Db::all(
            'SELECT l.name, l.code, l.x, l.y, l.lat, l.lng, l.status, l.verified, l.usage_count,
                    a.name AS area_name
               FROM locations l LEFT JOIN areas a ON a.id = l.area_id
              WHERE l.site_id = ? ORDER BY l.name',
            [(int) $site['id']]
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="locations-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'wb');
        fputcsv($out, ['name', 'code', 'x', 'y', 'lat', 'lng', 'status', 'verified', 'usage_count', 'area']);
        foreach ($rows as $row) {
            fputcsv($out, array_values($row));
        }
        fclose($out);
        exit;
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        $location = Db::first('SELECT * FROM locations WHERE id = ?', [$id]);
        if ($location === null) {
            Http::notFound('Location not found.');
        }

        return $location;
    }
}
